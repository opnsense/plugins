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

    diagnostics helpers for the PPPoE server plugin.  every output path
    masks secrets: mpd.secret is never shown, RADIUS shared secrets and the
    console credential are replaced before anything leaves this script.
"""

import argparse
import importlib.util
import json
import os
import re
import subprocess
import sys

CONF_DIR = '/usr/local/etc/pppoe_server'
MPD_CONF = os.path.join(CONF_DIR, 'mpd.conf')

_spec = importlib.util.spec_from_file_location(
    'pppoe_console', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'pppoe_console.py'))
pppoe_console = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pppoe_console)

MASK_RULES = (
    # set radius server <host> <secret> [ports] -> mask the secret column
    (re.compile(r'^(\s*set radius server\s+\S+\s+)\S+', re.M), r'\1********'),
    # set user <name> <password> [priv] -> mask the password column
    (re.compile(r'^(\s*set user\s+\S+\s+)\S+', re.M), r'\1********'),
)


def mask_config(text):
    for pattern, replacement in MASK_RULES:
        text = pattern.sub(replacement, text)
    return text


def run_cmd(cmd, timeout=15):
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, check=False)
        return proc.returncode, proc.stdout.strip(), proc.stderr.strip()
    except (OSError, subprocess.SubprocessError) as exc:
        return 1, '', str(exc)


def config_preview():
    try:
        with open(MPD_CONF, 'r', encoding='utf-8') as handle:
            return {'status': 'ok', 'preview': mask_config(handle.read())}
    except OSError as exc:
        return {'status': 'failed', 'message': str(exc)}


def netgraph_status():
    code, out, err = run_cmd(['/usr/sbin/ngctl', 'list'])
    if code != 0:
        return {'status': 'failed', 'message': err or 'ngctl failed'}
    nodes = [n for n in out.splitlines() if n.strip().startswith('Name:')]
    pppoe_related = [n.strip() for n in nodes
                     if re.search(r'Type:\s*(pppoe|ppp|iface|ether)\b', n, re.I)]
    return {'status': 'ok', 'nodes': len(nodes), 'listing': '\n'.join(pppoe_related)}


def versions():
    result = {'status': 'ok'}
    code, out, _ = run_cmd(['/usr/sbin/pkg', 'query', '%v', 'mpd5'])
    result['mpd5'] = out if code == 0 else 'not installed'
    code, out, _ = run_cmd(['/usr/sbin/pkg', 'query', '%v', 'os-pppoe-server'])
    result['plugin'] = out if code == 0 else 'development'
    code, out, _ = run_cmd(['/usr/bin/uname', '-rs'])
    result['os'] = out if code == 0 else ''
    return result


def validate():
    problems = []
    if not os.path.isfile(MPD_CONF):
        problems.append('mpd.conf is missing - apply the configuration first')
    else:
        with open(MPD_CONF, 'r', encoding='utf-8') as handle:
            conf = handle.read()
        for keyword in ('startup:', 'pppoe_server:'):
            if keyword not in conf:
                problems.append('mpd.conf lacks the %s section' % keyword)
        ifaces = set(re.findall(r'^\s*set pppoe iface\s+(\S+)', conf, re.M))
        code, out, _ = run_cmd(['/sbin/ifconfig', '-l'])
        present = set(out.split()) if code == 0 else set()
        for iface in sorted(ifaces - present):
            problems.append('interface %s not present on this system' % iface)
        for name in ('mpd.secret', 'console.auth'):
            path = os.path.join(CONF_DIR, name)
            if os.path.isfile(path):
                mode = os.stat(path).st_mode & 0o777
                if mode & 0o077:
                    problems.append('%s permissions too open (%o)' % (name, mode))
    status = 'ok' if not problems else 'failed'
    return {'status': status, 'problems': problems}


def support_bundle():
    """masked, secret-free diagnostic snapshot"""
    bundle = {'status': 'ok', 'sections': {}}
    bundle['sections']['versions'] = versions()
    bundle['sections']['validate'] = validate()
    bundle['sections']['netgraph'] = netgraph_status()
    bundle['sections']['config_preview'] = config_preview()
    code, out, _ = run_cmd(['/usr/local/etc/rc.d/pppoe_server', 'status'])
    bundle['sections']['service'] = {'status': 'ok', 'output': out}
    try:
        host, port, username, password = pppoe_console.read_auth()
        client = pppoe_console.ConsoleClient(host, port, username, password)
        try:
            sessions = pppoe_console.parse_sessions(client.run('show sessions'))
            pools = pppoe_console.parse_ippool(client.run('show ippool'))
        finally:
            client.close()
        bundle['sections']['sessions'] = {'status': 'ok', 'count': len(sessions)}
        bundle['sections']['pools'] = {'status': 'ok', 'pools': pools}
    except (OSError, ValueError, ConnectionError) as exc:
        bundle['sections']['sessions'] = {'status': 'failed', 'message': str(exc)}
    return bundle


def metrics():
    """Prometheus text exposition (low cardinality: bundle label only)"""
    lines = []

    def emit(name, value, help_text, mtype='gauge', labels=''):
        if help_text:
            lines.append('# HELP %s %s' % (name, help_text))
            lines.append('# TYPE %s %s' % (name, mtype))
        lines.append('%s%s %s' % (name, labels, value))

    service_up = 1 if run_cmd(['/usr/local/etc/rc.d/pppoe_server', 'status'])[0] == 0 else 0
    emit('pppoe_service_up', service_up, 'PPPoE server daemon running')

    try:
        host, port, username, password = pppoe_console.read_auth()
        client = pppoe_console.ConsoleClient(host, port, username, password)
        try:
            sessions = pppoe_console.parse_sessions(client.run('show sessions'))
            pools = pppoe_console.parse_ippool(client.run('show ippool'))
        finally:
            client.close()
    except (OSError, ValueError, ConnectionError):
        sessions, pools = [], []

    emit('pppoe_active_sessions', len(sessions), 'Active PPPoE sessions')
    by_bundle = {}
    for session in sessions:
        by_bundle[session['bundle']] = by_bundle.get(session['bundle'], 0) + 1
    first = True
    for bundle, count in sorted(by_bundle.items()):
        emit('pppoe_sessions_by_ac', count,
             'Active sessions per access concentrator' if first else '',
             labels='{bundle="%s"}' % bundle)
        first = False

    counters = pppoe_console.get_counters()
    total_in = sum(counters.get(s['iface'], {}).get('input_bytes', 0) for s in sessions)
    total_out = sum(counters.get(s['iface'], {}).get('output_bytes', 0) for s in sessions)
    emit('pppoe_input_bytes_total', total_in, 'Bytes received from clients', 'counter')
    emit('pppoe_output_bytes_total', total_out, 'Bytes sent to clients', 'counter')

    first = True
    for pool in pools:
        labels = '{pool="%s"}' % pool['name']
        emit('pppoe_pool_addresses_used', pool['used'],
             'Addresses in use per pool' if first else '', labels=labels)
        lines.append('pppoe_pool_addresses_total%s %s' % (labels, pool['total']))
        first = False

    return '\n'.join(lines) + '\n'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('command', choices=[
        'config_preview', 'netgraph_status', 'versions', 'validate',
        'support_bundle', 'metrics',
    ])
    args = parser.parse_args()

    if args.command == 'metrics':
        sys.stdout.write(metrics())
        return 0

    dispatch = {
        'config_preview': config_preview,
        'netgraph_status': netgraph_status,
        'versions': versions,
        'validate': validate,
        'support_bundle': support_bundle,
    }
    result = dispatch[args.command]()
    # exit 0 with JSON status; configd script_output drops output on failure exit
    print(json.dumps(result))
    return 0


if __name__ == '__main__':
    sys.exit(main())
