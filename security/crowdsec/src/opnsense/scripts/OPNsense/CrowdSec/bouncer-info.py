#!/usr/bin/env python3

# SPDX-License-Identifier: MIT
# SPDX-FileCopyrightText: © 2026 Michael Pihlblad

# Report on the firewall bouncer (remediation component), as seen from this
# firewall: works when it connects to a local or a remote LAPI.
#
#   bouncer-info.py status     version, LAPI url, blocklist sizes, log tail
#   bouncer-info.py blocklist  addresses currently blocked by pf
#   bouncer-info.py test       check the LAPI url and API key

import json
import os
import ssl
import subprocess
import sys
import urllib.error
import urllib.request
from typing import Any
import yaml

BOUNCER_BIN = '/usr/local/bin/crowdsec-firewall-bouncer'
BOUNCER_CONFIG = '/usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml'
LOG_TAIL_LINES = 20
TEST_TIMEOUT = 5


def load_config() -> dict[str, Any]:
    with open(BOUNCER_CONFIG) as fin:
        return yaml.safe_load(fin) or {}


def blocklist_tables(config: dict[str, Any]) -> dict[str, str]:
    return {
        'ipv4': config.get('blacklists_ipv4', 'crowdsec_blocklists'),
        'ipv6': config.get('blacklists_ipv6', 'crowdsec6_blocklists'),
    }


def table_entries(table: str) -> list[str]:
    try:
        p = subprocess.run(['/sbin/pfctl', '-t', table, '-T', 'show'],
                           text=True, capture_output=True, timeout=30)
    except (OSError, subprocess.TimeoutExpired):
        return []
    if p.returncode != 0:
        return []
    return [line.strip() for line in p.stdout.splitlines() if line.strip()]


def bouncer_version() -> str:
    try:
        p = subprocess.run([BOUNCER_BIN, '-version'], text=True, capture_output=True, timeout=5)
    except (OSError, subprocess.TimeoutExpired):
        return ''
    for line in (p.stdout + p.stderr).splitlines():
        if line.lower().startswith('version:'):
            return line.split(':', 1)[1].strip()
    return ''


def log_tail(config: dict[str, Any]) -> list[str]:
    filename = os.path.join(config.get('log_dir', '/var/log/crowdsec'), 'crowdsec-firewall-bouncer.log')
    try:
        with open(filename, errors='replace') as fin:
            return [line.rstrip('\n') for line in fin.readlines()[-LOG_TAIL_LINES:]]
    except OSError:
        return []


def status(config: dict[str, Any]) -> dict[str, Any]:
    return {
        'version': bouncer_version(),
        # never the api key
        'api_url': config.get('api_url', ''),
        'blocklists': {family: len(table_entries(table))
                       for family, table in blocklist_tables(config).items()},
        'log': log_tail(config),
    }


def blocklist(config: dict[str, Any]) -> list[dict[str, str]]:
    return [{'address': address, 'family': family}
            for family, table in blocklist_tables(config).items()
            for address in table_entries(table)]


def test(config: dict[str, Any]) -> dict[str, Any]:
    url = config.get('api_url', '')
    if not url:
        return {'result': 'error', 'message': 'no LAPI url configured'}

    # HEAD only checks the API key. A GET would update the "last pull" time of
    # the bouncer on the LAPI, which the bouncer's own stream relies on.
    request = urllib.request.Request(url.rstrip('/') + '/v1/decisions', method='HEAD',
                                     headers={'X-Api-Key': str(config.get('api_key', ''))})

    context = ssl.create_default_context(cafile=config.get('ca_cert_path') or None)
    if config.get('insecure_skip_verify'):
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE

    try:
        with urllib.request.urlopen(request, timeout=TEST_TIMEOUT, context=context) as response:
            code = response.status
    except urllib.error.HTTPError as e:
        code = e.code
    except (urllib.error.URLError, OSError, ValueError) as e:
        reason = getattr(e, 'reason', e)
        return {'result': 'unreachable', 'message': str(reason)}

    if code == 200:
        return {'result': 'ok', 'message': 'connected, API key accepted', 'http_code': code}
    if code == 403:
        return {'result': 'invalid_key', 'message': 'the API key was rejected', 'http_code': code}
    return {'result': 'error', 'message': 'unexpected HTTP status {}'.format(code), 'http_code': code}


def main():
    commands = {'status': status, 'blocklist': blocklist, 'test': test}
    if len(sys.argv) != 2 or sys.argv[1] not in commands:
        print('usage: {} {}'.format(sys.argv[0], '|'.join(commands)), file=sys.stderr)
        sys.exit(2)

    try:
        config = load_config()
    except (OSError, yaml.YAMLError) as e:
        print(json.dumps({'error': 'cannot read {}: {}'.format(BOUNCER_CONFIG, e)}))
        return

    print(json.dumps(commands[sys.argv[1]](config)))


if __name__ == '__main__':
    main()
