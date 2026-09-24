#!/usr/local/bin/python3
"""
Copyright (C) 2026 cayossarian (Bill Flood)
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

Avahi mDNS/DNS-SD Reflector diagnostics for the dashboard widget and API.
Always prints exactly one JSON object, so configd never hands the caller a
traceback.
"""
import glob
import json
import os
import re
import subprocess
from datetime import date, datetime

PID_FILE = '/var/run/avahi-daemon/pid'
CONF_FILE = '/usr/local/etc/avahi/avahi-daemon.conf'
# Written by syslog-ng through templates/OPNsense/Syslog/local/avahi.conf
LOG_FILE = '/var/log/avahi/latest.log'
SLOT_ERROR_PATTERN = 'No slot available for legacy unicast reflection'
MDNS_PORT = '5353'
AVAHI_COMMAND = 'avahi-daemon'
# plain sockstat output truncates COMMAND to this many characters
SOCKSTAT_COMMAND_WIDTH = 10

RFC5424_HEADER = re.compile(r'^<\d+>1 (\S+) ')


# --- pure parsing ------------------------------------------------------------

def summarize_slot_errors(lines, today):
    """Count slot-exhaustion lines stamped today; remember the last one.

    Lines are RFC 5424 as syslog-ng writes them with flags(syslog-protocol):
    <PRI>1 TIMESTAMP HOST APP PID MSGID SD MSG. Anything else is ignored.
    """
    today_iso = today.isoformat()
    summary = {
        'status': 'healthy',
        'slot_errors_today': 0,
        'last_slot_error': None,
    }
    for line in lines:
        if SLOT_ERROR_PATTERN not in line:
            continue
        match = RFC5424_HEADER.match(line)
        if match is None:
            continue
        timestamp = match.group(1)
        if timestamp[:10] == today_iso:
            summary['slot_errors_today'] += 1
        summary['last_slot_error'] = timestamp
    if summary['slot_errors_today'] > 0:
        summary['status'] = 'degraded'
    elif summary['last_slot_error'] is not None:
        summary['status'] = 'warning'
    return summary


def parse_conf(lines):
    """Read the settings the plugin renders into avahi-daemon.conf."""
    conf = {
        'configured': False,
        'domain': 'local',
        'interfaces': '',
        'reflector_enabled': False,
        'use_ipv4': True,
        'use_ipv6': False,
        'reflect_ipv': False,
        'reflect_filters': '',
    }
    for line in lines:
        line = line.strip()
        if line.startswith('#') or '=' not in line:
            continue
        conf['configured'] = True
        key, _, val = line.partition('=')
        key = key.strip()
        val = val.strip()
        if key == 'domain-name':
            conf['domain'] = val
        elif key == 'allow-interfaces':
            conf['interfaces'] = val
        elif key == 'enable-reflector':
            conf['reflector_enabled'] = val == 'yes'
        elif key == 'use-ipv4':
            conf['use_ipv4'] = val == 'yes'
        elif key == 'use-ipv6':
            conf['use_ipv6'] = val == 'yes'
        elif key == 'reflect-ipv':
            conf['reflect_ipv'] = val == 'yes'
        elif key == 'reflect-filters':
            conf['reflect_filters'] = val
    return conf


def _is_avahi(command):
    return command == AVAHI_COMMAND or command == AVAHI_COMMAND[:SOCKSTAT_COMMAND_WIDTH]


def _unique_sorted(commands):
    return sorted({c for c in commands if c and not _is_avahi(c)})


def parse_sockstat_json(text):
    """Non-avahi commands in `sockstat --libxo json` output (FreeBSD 15+).

    Raises ValueError when the text is not JSON.
    """
    data = json.loads(text)
    sockets = data.get('sockstat', {}).get('socket', []) if isinstance(data, dict) else []
    return _unique_sorted(
        str(s.get('command', '')) for s in sockets if isinstance(s, dict)
    )


def parse_sockstat_text(text):
    """Non-avahi commands in plain sockstat output (COMMAND is the 2nd column)."""
    commands = []
    for line in text.splitlines():
        fields = line.split()
        if len(fields) < 2 or fields[0] == 'USER':
            continue
        commands.append(fields[1])
    return _unique_sorted(commands)


# --- I/O ----------------------------------------------------------------------

def resolve_log(path):
    """latest.log once the hourly syslog archive job has linked it, else the
    newest dated file in the same directory, else the path itself."""
    if os.path.exists(path):
        return path
    dated = sorted(glob.glob(os.path.join(os.path.dirname(path), 'avahi_*.log')))
    return dated[-1] if dated else path


def read_slot_summary(path, today):
    try:
        with open(path, 'r', encoding='utf-8', errors='replace') as fh:
            return summarize_slot_errors(fh, today)
    except OSError:
        return summarize_slot_errors([], today)


def _read_conf():
    try:
        with open(CONF_FILE, 'r', encoding='utf-8', errors='replace') as fh:
            return parse_conf(fh)
    except OSError:
        return parse_conf([])


def _run(argv):
    """stdout of argv, or None when it cannot run or exits non-zero."""
    try:
        result = subprocess.run(argv, capture_output=True, text=True, errors='replace', timeout=5, check=False)
    except (OSError, subprocess.SubprocessError):
        return None
    return result.stdout if result.returncode == 0 else None


def mdns_conflicts():
    """Names of non-avahi processes listening on UDP 5353."""
    base = ['sockstat', '-4', '-6', '-l', '-P', 'udp', '-p', MDNS_PORT]
    out = _run([base[0], '--libxo', 'json'] + base[1:])
    if out is not None:
        try:
            return parse_sockstat_json(out)
        except ValueError:
            pass
    out = _run(base)
    return parse_sockstat_text(out) if out is not None else []


def _read_pid():
    try:
        with open(PID_FILE, 'r', encoding='utf-8', errors='replace') as fh:
            pid = int(fh.read().strip())
    except (OSError, ValueError):
        return None
    # kill(0, 0) would signal our own process group and look like success
    return pid if pid > 0 else None


def _process_running(pid):
    try:
        os.kill(pid, 0)
        return True
    except (OSError, OverflowError):
        return False


def _ps_field(pid, field):
    out = _run(['ps', '-o', field + '=', '-p', str(pid)])
    return out.strip() if out else None


def _process_start_time(pid):
    raw = _ps_field(pid, 'lstart')
    try:
        return datetime.strptime(raw, '%a %b %d %H:%M:%S %Y').strftime('%Y-%m-%d %H:%M:%S') if raw else None
    except ValueError:
        return None


def collect():
    pid = _read_pid()
    running = pid is not None and _process_running(pid)
    conf = _read_conf()

    health = read_slot_summary(resolve_log(LOG_FILE), date.today())
    health['last_restart'] = _process_start_time(pid) if running else None

    return {
        'running': running,
        'pid': pid if running else None,
        'configured': conf['configured'],
        'domain': conf['domain'],
        'interfaces': conf['interfaces'],
        'reflector_enabled': conf['reflector_enabled'],
        'use_ipv4': conf['use_ipv4'],
        'use_ipv6': conf['use_ipv6'],
        'reflect_ipv': conf['reflect_ipv'],
        'reflect_filters': conf['reflect_filters'],
        'conflicts': mdns_conflicts(),
        'health': health,
    }


def main():
    try:
        status = collect()
    except Exception as exc:  # the one catch-all: output must stay one JSON object
        status = {'status': 'error', 'message': f'{type(exc).__name__}: {exc}'}
    print(json.dumps(status))


if __name__ == '__main__':
    main()
