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

    RADIUS reachability test (RFC 2865): sends a PAP Access-Request for a
    throwaway probe account with a random password.  Any valid response
    (Access-Reject included) proves the server is reachable and the shared
    secret matches: a wrong secret makes real servers silently discard the
    request or fail response authentication.  No real credentials are used
    and no secret ever appears on the command line - servers and secrets
    are read from the generated mpd.conf (root only).
"""

import argparse
import hashlib
import hmac
import json
import os
import re
import secrets
import socket
import struct
import sys

MPD_CONF = '/usr/local/etc/pppoe_server/mpd.conf'
SERVER_RE = re.compile(
    r'^\s*set radius server\s+(?P<host>\S+)\s+(?P<secret>\S+)\s+(?P<authport>\d+)\s+(?P<acctport>\d+)\s*$'
)
HOST_RE = re.compile(r'^(?:\d{1,3}\.){3}\d{1,3}$')

ACCESS_REQUEST = 1
ACCESS_ACCEPT = 2
ACCESS_REJECT = 3

ATTR_USER_NAME = 1
ATTR_USER_PASSWORD = 2
ATTR_NAS_PORT_TYPE = 61
ATTR_MESSAGE_AUTHENTICATOR = 80

CODE_NAMES = {ACCESS_ACCEPT: 'Access-Accept', ACCESS_REJECT: 'Access-Reject', 11: 'Access-Challenge'}


def read_servers(path=MPD_CONF):
    servers = []
    with open(path, 'r', encoding='utf-8') as handle:
        for line in handle:
            matched = SERVER_RE.match(line)
            if matched:
                entry = matched.groupdict()
                if entry not in servers:
                    servers.append(entry)
    return servers


def attr(attr_type, value):
    return struct.pack('BB', attr_type, len(value) + 2) + value


def pap_encrypt(password, secret, authenticator):
    """RFC 2865 5.2 User-Password obfuscation"""
    password = password.ljust(16, b'\x00')
    digest = hashlib.md5(secret + authenticator).digest()
    return bytes(p ^ d for p, d in zip(password, digest))


def build_access_request(secret, authenticator, username, password):
    attrs = attr(ATTR_USER_NAME, username.encode())
    attrs += attr(ATTR_USER_PASSWORD, pap_encrypt(password.encode(), secret, authenticator))
    attrs += attr(ATTR_NAS_PORT_TYPE, struct.pack('!I', 15))  # Ethernet
    # Message-Authenticator: HMAC-MD5 over the packet with the attribute zeroed
    attrs += attr(ATTR_MESSAGE_AUTHENTICATOR, b'\x00' * 16)
    length = 20 + len(attrs)
    header = struct.pack('!BBH', ACCESS_REQUEST, 1, length) + authenticator
    mac = hmac.new(secret, header + attrs, hashlib.md5).digest()
    attrs = attrs[:-16] + mac
    return header + attrs


def verify_response(data, secret, request_authenticator):
    if len(data) < 20:
        return None
    code, ident, length = struct.unpack('!BBH', data[:4])
    if length > len(data):
        return None
    expected = hashlib.md5(
        data[:4] + request_authenticator + data[20:length] + secret
    ).digest()
    if not hmac.compare_digest(expected, data[4:20]):
        return None
    return code


def probe(server, timeout=5):
    secret = server['secret'].encode()
    authenticator = os.urandom(16)
    packet = build_access_request(
        secret, authenticator,
        'opnsense-probe', secrets.token_urlsafe(16)
    )
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.settimeout(timeout)
    try:
        sock.sendto(packet, (server['host'], int(server['authport'])))
        data, _ = sock.recvfrom(4096)
    except socket.timeout:
        return {'host': server['host'], 'status': 'timeout',
                'detail': 'no response (server down, port blocked or shared secret mismatch)'}
    except OSError as exc:
        return {'host': server['host'], 'status': 'error', 'detail': str(exc)}
    finally:
        sock.close()

    code = verify_response(data, secret, authenticator)
    if code is None:
        return {'host': server['host'], 'status': 'invalid',
                'detail': 'response failed authenticator check (shared secret mismatch)'}
    return {'host': server['host'], 'status': 'ok',
            'detail': 'received %s (server reachable, shared secret valid)'
                      % CODE_NAMES.get(code, 'code %d' % code)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--host', help='test only this configured server')
    args = parser.parse_args()

    # always exit 0 with JSON: configd script_output drops output on failure exit
    try:
        servers = read_servers()
    except OSError as exc:
        print(json.dumps({'status': 'failed', 'message': str(exc)}))
        return 0

    if args.host:
        if not HOST_RE.match(args.host):
            print(json.dumps({'status': 'failed', 'message': 'invalid host'}))
            return 0
        servers = [s for s in servers if s['host'] == args.host]

    if not servers:
        print(json.dumps({'status': 'failed', 'message': 'no RADIUS servers configured'}))
        return 0

    results = [probe(server) for server in servers]
    print(json.dumps({'status': 'ok', 'results': results}))
    return 0


if __name__ == '__main__':
    sys.exit(main())
