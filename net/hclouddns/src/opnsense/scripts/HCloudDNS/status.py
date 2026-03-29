#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Get status of HCloudDNS accounts
"""
import sys
import json
import os
import time
from xml.etree import ElementTree

STATE_PATH = '/var/cache/hclouddns'
CONFIG_PATH = '/conf/config.xml'


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

        general = hcloud.find('general')
        if general is not None:
            config['general'] = {
                'enabled': general.findtext('enabled', '0') == '1',
                'verbose': general.findtext('verbose', '0') == '1'
            }

        accounts = hcloud.find('accounts')
        if accounts is not None:
            for account in accounts.findall('account'):
                acc = {
                    'uuid': account.get('uuid', ''),
                    'enabled': account.findtext('enabled', '0') == '1',
                    'description': account.findtext('description', ''),
                    'zoneName': account.findtext('zoneName', ''),
                    'records': account.findtext('records', '').split(','),
                    'updateIPv4': account.findtext('updateIPv4', '1') == '1',
                    'updateIPv6': account.findtext('updateIPv6', '1') == '1',
                }
                acc['records'] = [r.strip() for r in acc['records'] if r.strip()]
                config['accounts'].append(acc)

        return config

    except Exception:
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


def format_time_ago(timestamp):
    """Format timestamp as human-readable time ago"""
    if not timestamp:
        return 'Never'

    diff = int(time.time()) - timestamp

    if diff < 60:
        return f"{diff} seconds ago"
    elif diff < 3600:
        return f"{diff // 60} minutes ago"
    elif diff < 86400:
        return f"{diff // 3600} hours ago"
    else:
        return f"{diff // 86400} days ago"


def main():
    config = get_config()

    result = {
        'enabled': False,
        'accounts': []
    }

    if config:
        result['enabled'] = config['general'].get('enabled', False)

        for account in config['accounts']:
            state = load_state(account['uuid'])

            acc_status = {
                'uuid': account['uuid'],
                'description': account['description'],
                'enabled': account['enabled'],
                'zone': account['zoneName'],
                'records': account['records'],
                'current_ipv4': state.get('ipv4', 'Unknown'),
                'current_ipv6': state.get('ipv6', 'Unknown'),
                'last_update': state.get('last_update', 0),
                'last_update_formatted': format_time_ago(state.get('last_update', 0)),
                'update_ipv4': account['updateIPv4'],
                'update_ipv6': account['updateIPv6']
            }
            result['accounts'].append(acc_status)

    print(json.dumps(result, indent=2))
    sys.exit(0)


if __name__ == '__main__':
    main()
