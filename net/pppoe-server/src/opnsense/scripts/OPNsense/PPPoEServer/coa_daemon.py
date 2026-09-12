#!/usr/local/bin/python3

"""
    Copyright (C) 2026 VEQNORA
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

    RFC 5176 Disconnect-Message adapter for mpd5.  mpd5 has no native CoA /
    Disconnect listener, so this small daemon speaks the RADIUS Dynamic
    Authorization protocol and translates a Disconnect-Request into an mpd5
    control-console session close (re-using the same console client the GUI
    uses).  It answers Disconnect-ACK / Disconnect-NAK with the correct
    response authenticator and honours a source allowlist and shared secret.

    Sessions are matched (in RFC-preferred order) by Acct-Session-Id, then
    User-Name, then Framed-IP-Address.  CoA-Request (reauthorize) is answered
    with CoA-NAK / Unsupported-Service because mpd5 cannot re-apply attributes
    to a live session; only Disconnect is actioned.
"""

import hashlib
import hmac
import ipaddress
import importlib.util
import os
import socket
import struct
import sys

CONF = '/usr/local/etc/pppoe_server/coa.conf'

_spec = importlib.util.spec_from_file_location(
    'pppoe_console', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'pppoe_console.py'))
pppoe_console = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pppoe_console)

# RADIUS Dynamic Authorization codes (RFC 5176 / RFC 3576)
DISCONNECT_REQUEST = 40
DISCONNECT_ACK = 41
DISCONNECT_NAK = 42
COA_REQUEST = 43
COA_ACK = 44
COA_NAK = 45

ATTR_USER_NAME = 1
ATTR_NAS_IP_ADDRESS = 4
ATTR_FRAMED_IP_ADDRESS = 8
ATTR_ACCT_SESSION_ID = 44
ATTR_MESSAGE_AUTHENTICATOR = 80
ATTR_ERROR_CAUSE = 101

# Error-Cause values (RFC 5176 section 3.5)
CAUSE_SESSION_NOT_FOUND = 503
CAUSE_UNSUPPORTED_SERVICE = 405


def read_conf(path=CONF):
    cfg = {'port': 3799, 'secret': b'', 'allowed': []}
    with open(path, 'r', encoding='ascii') as fh:
        for line in fh:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, val = line.split('=', 1)
            key, val = key.strip(), val.strip()
            if key == 'port':
                cfg['port'] = int(val)
            elif key == 'secret':
                cfg['secret'] = val.encode()
            elif key == 'allowed':
                for item in val.split(','):
                    item = item.strip()
                    if item:
                        cfg['allowed'].append(ipaddress.ip_network(item, strict=False))
    return cfg


def parse_attributes(data):
    """return list of (type, value_bytes) from a RADIUS attribute blob"""
    attrs = []
    i = 0
    while i + 2 <= len(data):
        atype = data[i]
        alen = data[i + 1]
        if alen < 2 or i + alen > len(data):
            break
        attrs.append((atype, data[i + 2:i + alen]))
        i += alen
    return attrs


def verify_request(packet, secret):
    """
    validate the Request Authenticator of a Disconnect/CoA request
    (RFC 5176 2.3): MD5(Code+ID+Length+RequestAuth(as received) is wrong;
    it is MD5(Code+ID+Length+16 zero octets replaced by request auth? )).
    Correct: the Request Authenticator = MD5(Code+ID+Length+Attributes+Secret)
    computed with the Authenticator field itself zeroed.
    """
    if len(packet) < 20:
        return False
    code, ident, length = struct.unpack('!BBH', packet[:4])
    authenticator = packet[4:20]
    attrs = packet[20:length] if length <= len(packet) else packet[20:]
    zeroed = packet[:4] + (b'\x00' * 16) + attrs
    expected = hashlib.md5(zeroed + secret).digest()
    if not hmac.compare_digest(expected, authenticator):
        return False
    # optional Message-Authenticator
    for atype, val in parse_attributes(attrs):
        if atype == ATTR_MESSAGE_AUTHENTICATOR:
            probe = bytearray(packet[:length])
            # zero the Message-Authenticator in place, then HMAC-MD5
            idx = probe.find(bytes([ATTR_MESSAGE_AUTHENTICATOR, 18]))
            if idx >= 0:
                for j in range(idx + 2, idx + 18):
                    probe[j] = 0
                mac = hmac.new(secret, bytes(probe), hashlib.md5).digest()
                if not hmac.compare_digest(mac, val):
                    return False
    return True


def build_response(code, ident, request_authenticator, secret, error_cause=None):
    attrs = b''
    if error_cause is not None:
        attrs += struct.pack('!BBI', ATTR_ERROR_CAUSE, 6, error_cause)
    length = 20 + len(attrs)
    header = struct.pack('!BBH', code, ident, length)
    resp_auth = hashlib.md5(header + request_authenticator + attrs + secret).digest()
    return header + resp_auth + attrs


def find_session(sessions, wanted):
    if wanted.get('session_id'):
        for s in sessions:
            if s['session_id'] == wanted['session_id']:
                return s
    if wanted.get('username'):
        for s in sessions:
            if s['username'] == wanted['username']:
                return s
    if wanted.get('address'):
        for s in sessions:
            if s['address'] == wanted['address']:
                return s
    return None


def disconnect(session):
    """close a session via the mpd console; return True on success"""
    try:
        host, port, user, pw = pppoe_console.read_auth()
        client = pppoe_console.ConsoleClient(host, port, user, pw)
        try:
            client.run('session %s' % session['session_id'])
            client.run('close')
        finally:
            client.close()
        return True
    except (OSError, ValueError, ConnectionError):
        return False


def handle(packet, addr, cfg, sock):
    src = ipaddress.ip_address(addr[0])
    if not any(src in net for net in cfg['allowed']):
        return  # silently drop unauthorised sources
    if len(packet) < 20:
        return
    code, ident, length = struct.unpack('!BBH', packet[:4])
    request_authenticator = packet[4:20]
    if not verify_request(packet, cfg['secret']):
        return  # bad secret / authenticator -> drop

    if code == COA_REQUEST:
        # mpd5 cannot re-apply attributes to a live session
        sock.sendto(build_response(COA_NAK, ident, request_authenticator,
                                   cfg['secret'], CAUSE_UNSUPPORTED_SERVICE), addr)
        return
    if code != DISCONNECT_REQUEST:
        return

    wanted = {}
    for atype, val in parse_attributes(packet[20:length]):
        if atype == ATTR_ACCT_SESSION_ID:
            sid = val.decode('ascii', 'ignore')
            if pppoe_console.SESSION_RE.match(sid):
                wanted['session_id'] = sid
        elif atype == ATTR_USER_NAME:
            name = val.decode('ascii', 'ignore')
            if pppoe_console.USERNAME_RE.match(name):
                wanted['username'] = name
        elif atype == ATTR_FRAMED_IP_ADDRESS and len(val) == 4:
            wanted['address'] = socket.inet_ntoa(val)

    try:
        host, port, user, pw = pppoe_console.read_auth()
        client = pppoe_console.ConsoleClient(host, port, user, pw)
        try:
            sessions = pppoe_console.parse_sessions(client.run('show sessions'))
        finally:
            client.close()
    except (OSError, ValueError, ConnectionError):
        sock.sendto(build_response(DISCONNECT_NAK, ident, request_authenticator,
                                   cfg['secret'], CAUSE_SESSION_NOT_FOUND), addr)
        return

    session = find_session(sessions, wanted)
    if session is not None and disconnect(session):
        sock.sendto(build_response(DISCONNECT_ACK, ident, request_authenticator,
                                   cfg['secret']), addr)
    else:
        sock.sendto(build_response(DISCONNECT_NAK, ident, request_authenticator,
                                   cfg['secret'], CAUSE_SESSION_NOT_FOUND), addr)


def main():
    try:
        cfg = read_conf()
    except (OSError, ValueError) as exc:
        sys.stderr.write('coa: bad config: %s\n' % exc)
        return 1
    if not cfg['secret'] or not cfg['allowed']:
        sys.stderr.write('coa: secret and at least one allowed client required\n')
        return 1

    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind(('0.0.0.0', cfg['port']))
    sys.stderr.write('coa: listening on udp/%d\n' % cfg['port'])
    while True:
        try:
            packet, addr = sock.recvfrom(4096)
        except OSError:
            continue
        try:
            handle(packet, addr, cfg, sock)
        except Exception as exc:  # never let one packet kill the daemon
            sys.stderr.write('coa: error handling packet from %s: %s\n' % (addr, exc))


if __name__ == '__main__':
    sys.exit(main())
