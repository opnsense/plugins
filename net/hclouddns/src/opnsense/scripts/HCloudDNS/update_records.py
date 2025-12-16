#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Update DNS records for HCloudDNS - reads config from OPNsense model
"""
import sys
import json
import os
import syslog
import subprocess
import re
from xml.etree import ElementTree

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI

CONFIG_PATH = '/conf/config.xml'
STATE_PATH = '/var/cache/hclouddns'


def parse_ttl(ttl_raw):
    """Parse TTL value from config - handles '_60' format and plain '60'"""
    if not ttl_raw:
        return 300
    # Handle OptionField format: "_60" -> 60 or "opt60" -> 60
    if ttl_raw.startswith('_'):
        ttl_raw = ttl_raw[1:]
    elif ttl_raw.startswith('opt'):
        ttl_raw = ttl_raw[3:]
    try:
        return int(ttl_raw)
    except ValueError:
        return 300


def get_config():
    """Read HCloudDNS configuration from OPNsense config.xml"""
    try:
        tree = ElementTree.parse(CONFIG_PATH)
        root = tree.getroot()

        hcloud = root.find('.//OPNsense/HCloudDNS')
        if hcloud is None:
            return None

        config = {
            'general': {},
            'accounts': []
        }

        # Parse general settings
        general = hcloud.find('general')
        if general is not None:
            config['general'] = {
                'enabled': general.findtext('enabled', '0') == '1',
                'checkInterval': int(general.findtext('checkInterval', '300')),
                'forceInterval': int(general.findtext('forceInterval', '0')),
                'verbose': general.findtext('verbose', '0') == '1'
            }

        # Parse accounts
        accounts = hcloud.find('accounts')
        if accounts is not None:
            for account in accounts.findall('account'):
                acc = {
                    'uuid': account.get('uuid', ''),
                    'enabled': account.findtext('enabled', '0') == '1',
                    'description': account.findtext('description', ''),
                    'apiToken': account.findtext('apiToken', ''),
                    'zoneId': account.findtext('zoneId', ''),
                    'zoneName': account.findtext('zoneName', ''),
                    'records': account.findtext('records', '').split(','),
                    'updateIPv4': account.findtext('updateIPv4', '1') == '1',
                    'updateIPv6': account.findtext('updateIPv6', '1') == '1',
                    'checkip': account.findtext('checkip', 'if'),
                    'checkipInterface': account.findtext('checkipInterface', ''),
                    'ttl': parse_ttl(account.findtext('ttl', '300'))
                }
                # Filter empty records
                acc['records'] = [r.strip() for r in acc['records'] if r.strip()]
                config['accounts'].append(acc)

        return config

    except Exception as e:
        syslog.syslog(syslog.LOG_ERR, f"HCloudDNS: Failed to read config: {str(e)}")
        return None


def get_current_ip(method, interface=None, ip_version=4):
    """Get current public IP address"""
    if method == 'if' and interface:
        # Get IP from interface
        try:
            family = 'inet6' if ip_version == 6 else 'inet'
            cmd = f"ifconfig {interface} | grep '{family} ' | head -1"
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True)

            if result.returncode == 0 and result.stdout:
                # Parse IP from ifconfig output
                line = result.stdout.strip()
                if ip_version == 6:
                    # inet6 fe80::1%em0 prefixlen 64 scopeid 0x1
                    match = re.search(r'inet6\s+([0-9a-fA-F:]+)', line)
                    if match:
                        ip = match.group(1)
                        # Skip link-local addresses
                        if not ip.startswith('fe80'):
                            return ip
                else:
                    # inet 192.168.1.1 netmask 0xffffff00 broadcast 192.168.1.255
                    match = re.search(r'inet\s+(\d+\.\d+\.\d+\.\d+)', line)
                    if match:
                        return match.group(1)
        except Exception as e:
            syslog.syslog(syslog.LOG_ERR, f"HCloudDNS: Failed to get IP from interface: {str(e)}")

    else:
        # Use web service
        services = {
            'web_ipify': ('https://api.ipify.org', 'https://api6.ipify.org'),
            'web_ip4only': ('https://ip4only.me/api/', None),
            'web_ip6only': (None, 'https://ip6only.me/api/'),
            'web_dyndns': ('http://checkip.dyndns.org', None),
            'web_freedns': ('https://freedns.afraid.org/dynamic/check.php', None),
            'web_he': ('http://checkip.dns.he.net', None),
        }

        urls = services.get(method, ('https://api.ipify.org', 'https://api6.ipify.org'))
        url = urls[1] if ip_version == 6 else urls[0]

        if url:
            try:
                import requests
                response = requests.get(url, timeout=10)
                if response.status_code == 200:
                    # Extract IP from response
                    text = response.text.strip()
                    if ip_version == 6:
                        match = re.search(r'([0-9a-fA-F:]+:[0-9a-fA-F:]+)', text)
                    else:
                        match = re.search(r'(\d+\.\d+\.\d+\.\d+)', text)
                    if match:
                        return match.group(1)
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, f"HCloudDNS: Failed to get IP from {url}: {str(e)}")

    return None


def load_state(account_uuid):
    """Load last known state for an account"""
    state_file = os.path.join(STATE_PATH, f"{account_uuid}.json")
    try:
        if os.path.exists(state_file):
            with open(state_file, 'r') as f:
                return json.load(f)
    except Exception:
        pass
    return {'ipv4': None, 'ipv6': None, 'last_update': 0}


def save_state(account_uuid, state):
    """Save state for an account"""
    os.makedirs(STATE_PATH, exist_ok=True)
    state_file = os.path.join(STATE_PATH, f"{account_uuid}.json")
    try:
        with open(state_file, 'w') as f:
            json.dump(state, f)
    except Exception as e:
        syslog.syslog(syslog.LOG_ERR, f"HCloudDNS: Failed to save state: {str(e)}")


def update_account(account, verbose=False):
    """Update DNS records for a single account"""
    results = []

    api = HCloudAPI(account['apiToken'], verbose=verbose)

    # Get current IPs
    current_ipv4 = None
    current_ipv6 = None

    if account['updateIPv4']:
        current_ipv4 = get_current_ip(account['checkip'], account['checkipInterface'], 4)
        if verbose and current_ipv4:
            syslog.syslog(syslog.LOG_INFO, f"HCloudDNS: Current IPv4: {current_ipv4}")

    if account['updateIPv6']:
        current_ipv6 = get_current_ip(account['checkip'], account['checkipInterface'], 6)
        if verbose and current_ipv6:
            syslog.syslog(syslog.LOG_INFO, f"HCloudDNS: Current IPv6: {current_ipv6}")

    if not current_ipv4 and not current_ipv6:
        syslog.syslog(syslog.LOG_WARNING, f"HCloudDNS: [{account['description']}] No IP address detected")
        return [{'status': 'error', 'message': 'No IP address detected'}]

    # Load previous state
    state = load_state(account['uuid'])

    # Check if update needed
    ipv4_changed = current_ipv4 and current_ipv4 != state.get('ipv4')
    ipv6_changed = current_ipv6 and current_ipv6 != state.get('ipv6')

    if not ipv4_changed and not ipv6_changed:
        if verbose:
            syslog.syslog(syslog.LOG_INFO, f"HCloudDNS: [{account['description']}] No IP change detected")
        return [{'status': 'ok', 'message': 'No update needed'}]

    # Update each record
    for record_spec in account['records']:
        # record_spec format: "name:type" e.g. "www:A" or "@:AAAA"
        if ':' in record_spec:
            name, rtype = record_spec.split(':', 1)
        else:
            # Default: update both A and AAAA
            name = record_spec
            rtype = None

        # Determine which updates to perform
        updates = []
        if rtype:
            if rtype == 'A' and current_ipv4 and ipv4_changed:
                updates.append(('A', current_ipv4))
            elif rtype == 'AAAA' and current_ipv6 and ipv6_changed:
                updates.append(('AAAA', current_ipv6))
        else:
            if current_ipv4 and ipv4_changed:
                updates.append(('A', current_ipv4))
            if current_ipv6 and ipv6_changed:
                updates.append(('AAAA', current_ipv6))

        for record_type, ip in updates:
            success, message = api.update_record(
                account['zoneId'],
                name,
                record_type,
                ip,
                account['ttl']
            )

            result = {
                'record': f"{name}.{account['zoneName']}",
                'type': record_type,
                'ip': ip,
                'status': 'ok' if success else 'error',
                'message': message
            }
            results.append(result)

            if success:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    f"HCloudDNS: [{account['description']}] Updated {name} {record_type} -> {ip}"
                )
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    f"HCloudDNS: [{account['description']}] Failed to update {name} {record_type}: {message}"
                )

    # Save state if any updates succeeded
    if any(r['status'] == 'ok' for r in results):
        if current_ipv4 and ipv4_changed:
            state['ipv4'] = current_ipv4
        if current_ipv6 and ipv6_changed:
            state['ipv6'] = current_ipv6
        import time
        state['last_update'] = int(time.time())
        save_state(account['uuid'], state)

    return results


def main():
    syslog.openlog('HCloudDNS', syslog.LOG_PID, syslog.LOG_DAEMON)

    config = get_config()
    if not config:
        print(json.dumps({
            'status': 'error',
            'message': 'Failed to read configuration'
        }))
        sys.exit(1)

    if not config['general'].get('enabled', False):
        print(json.dumps({
            'status': 'ok',
            'message': 'HCloudDNS is disabled'
        }))
        sys.exit(0)

    verbose = config['general'].get('verbose', False)
    all_results = []

    for account in config['accounts']:
        if not account['enabled']:
            continue

        if verbose:
            syslog.syslog(syslog.LOG_INFO, f"HCloudDNS: Processing account [{account['description']}]")

        results = update_account(account, verbose)
        all_results.append({
            'account': account['description'],
            'results': results
        })

    print(json.dumps({
        'status': 'ok',
        'accounts': all_results
    }))
    sys.exit(0)


if __name__ == '__main__':
    main()
