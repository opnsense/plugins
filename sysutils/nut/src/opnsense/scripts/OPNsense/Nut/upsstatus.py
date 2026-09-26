#!/usr/bin/env python3

"""
    Copyright (c) 2026 Tore Amundsen <tore@amundsen.org>
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

    --------------------------------------------------------------------------
    list the variables of one or more UPS devices as json, e.g.
    upsstatus.py myups,otherups@host:3493

    upsd is queried directly because upsc from NUT 2.8.5 crashes on
    FreeBSD 14 (https://github.com/networkupstools/nut/issues/3454).
"""

import json
import socket
import sys


def parse_target(target):
    """ split 'upsname[@host[:port]]' into its parts, allowing [ipv6] hosts """
    upsname, _, host = target.partition('@')
    port = 3493
    if host.startswith('['):
        host, _, rest = host[1:].partition(']')
        if rest.startswith(':') and rest[1:].isdigit():
            port = int(rest[1:])
    elif host.count(':') == 1 and host.split(':')[1].isdigit():
        host, port = host.split(':')[0], int(host.split(':')[1])
    return upsname, host if host else '127.0.0.1', port


def unquote(value):
    if value.startswith('"') and value.endswith('"'):
        value = value[1:-1].replace('\\"', '"').replace('\\\\', '\\')
    return value


def list_vars(conn, reader, upsname):
    """ return the variables of a UPS in upsc output format """
    conn.sendall(('LIST VAR %s\n' % upsname).encode())
    result = []
    while True:
        line = reader.readline()
        if not line:
            return 'Error: connection closed'
        line = line.decode(errors='replace').rstrip('\n')
        if line.startswith('ERR '):
            return 'Error: %s' % line[4:]
        if line.startswith('END LIST VAR'):
            return '\n'.join(result)
        parts = line.split(' ', 3)
        if len(parts) == 4 and parts[0] == 'VAR' and parts[1] == upsname:
            result.append('%s: %s' % (parts[2], unquote(parts[3])))


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print('usage: %s <upsname[@host[:port]],...>' % sys.argv[0], file=sys.stderr)
        sys.exit(1)
    hosts = {}
    for target in sys.argv[1].split(','):
        upsname, host, port = parse_target(target)
        hosts.setdefault((host, port), []).append(upsname)
    items = []
    for (host, port), upslist in hosts.items():
        statuses = {}
        try:
            with socket.create_connection((host, port), timeout=5) as conn:
                reader = conn.makefile('rb')
                for upsname in upslist:
                    statuses[upsname] = list_vars(conn, reader, upsname)
                conn.sendall(b'LOGOUT\n')
        except OSError as error:
            for upsname in upslist:
                statuses.setdefault(upsname, 'Error: %s' % error)
        for upsname in upslist:
            items.append({'name': upsname, 'status': statuses[upsname]})
    print(json.dumps(items))
