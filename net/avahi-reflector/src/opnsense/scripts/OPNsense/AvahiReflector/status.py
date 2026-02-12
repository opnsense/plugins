#!/usr/local/bin/python3

"""
Avahi mDNS/DNS-SD Reflector — diagnostics script.
Returns JSON status for the OPNsense dashboard widget and API.
"""

import json
import os
import subprocess

PID_FILE = '/var/run/avahi-daemon/pid'
CONF_FILE = '/usr/local/etc/avahi/avahi-daemon.conf'


def _read_pid():
    try:
        with open(PID_FILE, 'r') as fh:
            return int(fh.read().strip())
    except (FileNotFoundError, ValueError):
        return None


def _process_running(pid):
    try:
        os.kill(pid, 0)
        return True
    except (OSError, TypeError):
        return False


def _process_uptime(pid):
    try:
        result = subprocess.run(
            ['ps', '-o', 'etime=', '-p', str(pid)],
            capture_output=True, text=True, timeout=5
        )
        return result.stdout.strip() if result.returncode == 0 else None
    except Exception:
        return None


def _process_memory_mb(pid):
    try:
        result = subprocess.run(
            ['ps', '-o', 'rss=', '-p', str(pid)],
            capture_output=True, text=True, timeout=5
        )
        if result.returncode == 0:
            rss_kb = int(result.stdout.strip())
            return round(rss_kb / 1024)
    except Exception:
        pass
    return None


def _parse_conf():
    conf = {
        'domain': 'local',
        'interfaces': '',
        'reflector_enabled': False,
        'use_ipv4': True,
        'use_ipv6': False,
        'reflect_ipv': False,
        'reflect_filters': '',
    }
    try:
        with open(CONF_FILE, 'r') as fh:
            for line in fh:
                line = line.strip()
                if line.startswith('#') or '=' not in line:
                    continue
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
    except FileNotFoundError:
        pass
    return conf


def _mdns_repeater_running():
    try:
        result = subprocess.run(
            ['pgrep', '-x', 'mdns-repeater'],
            capture_output=True, timeout=5
        )
        return result.returncode == 0
    except Exception:
        return False


def main():
    pid = _read_pid()
    running = pid is not None and _process_running(pid)
    conf = _parse_conf()

    status = {
        'running': running,
        'pid': pid if running else None,
        'uptime': _process_uptime(pid) if running else None,
        'memory_mb': _process_memory_mb(pid) if running else None,
        'domain': conf['domain'],
        'interfaces': conf['interfaces'],
        'reflector_enabled': conf['reflector_enabled'],
        'use_ipv4': conf['use_ipv4'],
        'use_ipv6': conf['use_ipv6'],
        'reflect_ipv': conf['reflect_ipv'],
        'reflect_filters': conf['reflect_filters'],
        'mdns_repeater_running': _mdns_repeater_running(),
    }

    print(json.dumps(status))


if __name__ == '__main__':
    main()
