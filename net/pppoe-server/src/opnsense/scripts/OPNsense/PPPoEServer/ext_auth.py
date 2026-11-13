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

    mpd5 external authentication backend for local users.  This is used
    instead of mpd.secret when "local shaping" is enabled, because mpd5
    applies per-session rate limits (ng_car) only from auth parameters
    (RADIUS or ext-auth) — not from the secrets file.

    Protocol (mpd5 auth.c): mpd writes request lines to our stdin
    (USER_NAME, AUTH_TYPE, ...) ending with a blank line; we write reply
    attributes to stdout ending with a blank line.  We return the user's
    cleartext password (mpd verifies PAP/CHAP itself), an optional static
    Framed-IP-Address, and MPD_LIMIT rules built from the per-user
    upload/download limits.  Passwords are read from a root-only data file
    and never logged.
"""

import re
import sys

DATA = '/usr/local/etc/pppoe_server/users.ext'
USERNAME_RE = re.compile(r'^[0-9a-zA-Z._@-]{1,64}$')


def read_request(stream):
    # read line-by-line (NOT `for line in stream`, whose block buffering would
    # deadlock: mpd holds the pipe open waiting for our reply after the blank
    # line, so the iterator's read-ahead would block forever)
    req = {}
    while True:
        line = stream.readline()
        if line == '':          # EOF
            break
        line = line.rstrip('\n')
        if line == '':          # blank line = end of request
            break
        if ':' in line:
            key, val = line.split(':', 1)
            req[key] = val
    return req


def lookup(username):
    """return dict with password/up/down/staticip or None"""
    try:
        with open(DATA, 'r', encoding='utf-8') as fh:
            for line in fh:
                parts = line.rstrip('\n').split('\t')
                if len(parts) >= 5 and parts[0] == username:
                    return {
                        'password': parts[1],
                        'uplimit': int(parts[2] or 0),
                        'downlimit': int(parts[3] or 0),
                        'staticip': parts[4],
                        'ip6route': parts[5] if len(parts) >= 6 else '',
                    }
    except (OSError, ValueError):
        return None
    return None


def limit_rule(direction, kbit):
    """build an mpd MPD_LIMIT rule string for a kbit/s rate"""
    bps = int(kbit) * 1000
    burst = max(3000, bps // 100)  # ~10 ms of data, floor 3 kB
    return '%s#1=all rate-limit %d %d' % (direction, bps, burst)


def main():
    req = read_request(sys.stdin)
    username = req.get('USER_NAME', '')
    out = sys.stdout

    if not USERNAME_RE.match(username):
        out.write('RESULT:FAIL\n\n')
        return 0

    user = lookup(username)
    if user is None:
        out.write('RESULT:FAIL\n\n')
        return 0

    # hand mpd the cleartext password; it performs the PAP/CHAP check
    out.write('USER_PASSWORD:%s\n' % user['password'])
    out.write('RESULT:UNDEF\n')
    if user['staticip']:
        out.write('FRAMED_IP_ADDRESS:%s\n' % user['staticip'])
    if user.get('ip6route'):
        # route a static IPv6 prefix towards this subscriber (see docs/IPV6.md)
        out.write('FRAMED_IPV6_ROUTE:%s\n' % user['ip6route'])
    if user['downlimit'] > 0:
        out.write('MPD_LIMIT:%s\n' % limit_rule('in', user['downlimit']))
    if user['uplimit'] > 0:
        out.write('MPD_LIMIT:%s\n' % limit_rule('out', user['uplimit']))
    out.write('\n')
    out.flush()
    return 0


if __name__ == '__main__':
    sys.exit(main())
