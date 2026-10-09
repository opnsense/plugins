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

    mpd5 control console client: list active PPPoE sessions as JSON and
    disconnect sessions by session id or username.

    "show sessions" emits one TAB separated line per link (mpd 5.9
    command.c:ShowSessions): ifname, peer-ip, bundle, msession-id, link,
    link-id, session-id, username, peer-mac and, with the session-time
    global option enabled, session uptime in seconds.
"""

import argparse
import json
import re
import socket
import subprocess
import sys

AUTH_FILE = '/usr/local/etc/pppoe_server/console.auth'
SESSION_RE = re.compile(r'^[0-9A-Za-z._-]{1,32}$')
USERNAME_RE = re.compile(r'^[0-9a-zA-Z._@-]{1,64}$')
IAC = 255

SESSION_FIELDS = (
    'iface', 'address', 'bundle', 'msession_id', 'link',
    'link_id', 'session_id', 'username', 'peer_mac', 'uptime'
)


def parse_sessions(payload):
    """parse raw 'show sessions' output into a list of dicts"""
    sessions = []
    for line in payload.splitlines():
        parts = line.rstrip('\r').split('\t')
        # 9 columns without, 10 with the session-time option
        if len(parts) < 9 or not SESSION_RE.match(parts[6]):
            continue
        session = dict(zip(SESSION_FIELDS, parts[:10]))
        session.setdefault('uptime', '')
        sessions.append(session)
    return sessions


IPPOOL_RE = re.compile(r'^\s*(?P<name>[0-9A-Za-z_-]+):\s+used\s+(?P<used>\d+)\s+of\s+(?P<total>\d+)\s*$')


def parse_ippool(payload):
    """parse mpd5 'show ippool' output: '\t<name>:\tused    N of    M'"""
    pools = []
    for line in payload.splitlines():
        matched = IPPOOL_RE.match(line)
        if matched:
            used = int(matched.group('used'))
            total = int(matched.group('total'))
            pools.append({
                'name': matched.group('name'),
                'used': used,
                'total': total,
                'free': total - used,
            })
    return pools


def parse_counters(payload):
    """
    parse `netstat -ibn` link-level rows into per-interface counters.
    ng interfaces have no link address, which shifts the columns, so the
    trailing fixed fields are taken from the end of the line:
    ... Ipkts Ierrs Idrop Ibytes Opkts Oerrs Obytes Coll
    """
    counters = {}
    for line in payload.splitlines():
        parts = line.split()
        if len(parts) < 10 or '<Link#' not in line:
            continue
        try:
            counters[parts[0]] = {
                'input_packets': int(parts[-8]),
                'input_bytes': int(parts[-5]),
                'output_packets': int(parts[-4]),
                'output_bytes': int(parts[-2]),
            }
        except ValueError:
            continue
    return counters


def get_counters():
    try:
        output = subprocess.run(
            ['/usr/bin/netstat', '-ibn'],
            capture_output=True, text=True, timeout=10, check=False
        ).stdout
    except (OSError, subprocess.SubprocessError):
        return {}
    return parse_counters(output)


class ConsoleClient:
    """minimal telnet-ish client for the mpd5 control console"""

    PROMPT_RE = re.compile(r'\[[^\]]*\]\s*$')

    def __init__(self, host, port, username, password, timeout=5):
        self._sock = socket.create_connection((host, port), timeout=timeout)
        self._sock.settimeout(timeout)
        self._buf = b''
        self._expect(b'Username: ')
        self._send(username)
        self._expect(b'Password: ')
        self._send(password)
        self._await_login()

    def _await_login(self):
        """block until the daemon confirms (prompt) or rejects the login"""
        while True:
            text = self._buf.decode('utf-8', 'replace')
            if 'Login failed' in text:
                raise ConnectionError('console authentication failed')
            if 'Welcome!' in text and self.PROMPT_RE.search(text):
                self._buf = b''
                return
            self._buf += self._strip_telnet(self._read_chunk())

    def _send(self, line):
        # mpd5 treats CR and LF as separate line terminators, so a CRLF would
        # submit the line and then an empty second line (e.g. an empty
        # password right after the username). Use a single LF.
        self._sock.sendall(line.encode('ascii', 'ignore') + b'\n')

    def _read_chunk(self):
        chunk = self._sock.recv(4096)
        if chunk == b'':
            raise ConnectionError('console closed connection')
        return chunk

    @staticmethod
    def _strip_telnet(data):
        """drop IAC option negotiation sequences from a byte stream"""
        out = bytearray()
        i = 0
        while i < len(data):
            byte = data[i]
            if byte == IAC and i + 1 < len(data):
                verb = data[i + 1]
                if verb == IAC:
                    out.append(IAC)
                    i += 2
                elif verb in (251, 252, 253, 254) and i + 2 < len(data):
                    i += 3  # WILL/WONT/DO/DONT <option>
                elif verb == 250:  # SB ... SE
                    end = data.find(bytes((IAC, 240)), i)
                    i = len(data) if end < 0 else end + 2
                else:
                    i += 2
            else:
                out.append(byte)
                i += 1
        return bytes(out)

    def _expect(self, token):
        while token not in self._buf:
            self._buf += self._strip_telnet(self._read_chunk())
        pos = self._buf.find(token) + len(token)
        consumed, self._buf = self._buf[:pos], self._buf[pos:]
        return consumed

    def run(self, command):
        """execute one command, return its output up to the next prompt"""
        self._send(command)
        # every reply ends with a prompt like "[] " or "[B1] " on its own line
        output = []
        while True:
            self._buf += self._strip_telnet(self._read_chunk())
            text = self._buf.decode('utf-8', 'replace')
            lines = text.split('\n')
            if lines and self.PROMPT_RE.match(lines[-1]):
                self._buf = b''
                output = lines[:-1]
                break
        # first echoed line repeats the command itself
        if output and command in output[0]:
            output = output[1:]
        return '\n'.join(output)

    def close(self):
        try:
            self._send('quit')
        except OSError:
            pass
        self._sock.close()


def read_auth():
    with open(AUTH_FILE, 'r', encoding='ascii') as fh:
        for line in fh:
            parts = line.split()
            if len(parts) == 4:
                return parts[0], int(parts[1]), parts[2], parts[3]
    raise ValueError('malformed console.auth')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('command', choices=['sessions', 'disconnect', 'pool_status'])
    parser.add_argument('--session', help='session id to disconnect')
    parser.add_argument('--user', help='disconnect all sessions of a username')
    parser.add_argument('--bundle', help='disconnect all sessions on a bundle (access concentrator)')
    args = parser.parse_args()

    result = {'status': 'failed'}
    try:
        host, port, username, password = read_auth()
        client = ConsoleClient(host, port, username, password)
        try:
            if args.command == 'pool_status':
                pools = parse_ippool(client.run('show ippool'))
                print(json.dumps({'status': 'ok', 'pools': pools}))
                return 0
            sessions = parse_sessions(client.run('show sessions'))
            if args.command == 'sessions':
                counters = get_counters()
                for session in sessions:
                    session.update(counters.get(session['iface'], {
                        'input_packets': 0, 'input_bytes': 0,
                        'output_packets': 0, 'output_bytes': 0,
                    }))
                result = {'status': 'ok', 'sessions': sessions}
            else:
                targets = []
                if args.session:
                    if not SESSION_RE.match(args.session):
                        raise ValueError('invalid session id')
                    targets = [s for s in sessions if s['session_id'] == args.session]
                elif args.user:
                    if not USERNAME_RE.match(args.user):
                        raise ValueError('invalid username')
                    targets = [s for s in sessions if s['username'] == args.user]
                elif args.bundle:
                    if not SESSION_RE.match(args.bundle):
                        raise ValueError('invalid bundle name')
                    targets = [s for s in sessions if s['bundle'] == args.bundle]
                else:
                    raise ValueError('nothing to disconnect')
                for session in targets:
                    client.run('session %s' % session['session_id'])
                    client.run('close')
                result = {'status': 'ok', 'disconnected': len(targets)}
        finally:
            client.close()
    except (OSError, ValueError, ConnectionError) as exc:
        result = {'status': 'failed', 'message': str(exc)}

    # always exit 0 with a JSON status payload: configd script_output
    # discards output on a non-zero exit code, which would hide the error
    print(json.dumps(result))
    return 0


if __name__ == '__main__':
    sys.exit(main())
