#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Refresh status of all entries from Hetzner DNS API
"""

import json
import sys
import os
import xml.etree.ElementTree as ET

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def refresh_status():
    """Refresh status of all configured entries from Hetzner"""
    result = {
        'status': 'ok',
        'entries': [],
        'errors': []
    }

    try:
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()

        hcloud = root.find('.//OPNsense/HCloudDNS')
        if hcloud is None:
            return {'status': 'ok', 'entries': [], 'message': 'No configuration found'}

        # Load accounts (tokens)
        accounts = {}
        accounts_node = hcloud.find('accounts')
        if accounts_node is not None:
            for acc in accounts_node.findall('account'):
                acc_uuid = acc.get('uuid', '')
                if acc_uuid and acc.findtext('enabled', '1') == '1':
                    accounts[acc_uuid] = {
                        'token': acc.findtext('apiToken', ''),
                        'apiType': acc.findtext('apiType', 'cloud'),
                        'name': acc.findtext('name', '')
                    }

        # Get all entries
        entries_node = hcloud.find('entries')
        if entries_node is None:
            return {'status': 'ok', 'entries': [], 'message': 'No entries configured'}

        # Cache records by (account, zone_id) to minimize API calls
        zone_records_cache = {}
        api_cache = {}  # Cache API instances per account

        for entry in entries_node.findall('entry'):
            entry_uuid = entry.get('uuid', '')
            account_uuid = entry.findtext('account', '')
            zone_id = entry.findtext('zoneId', '')
            zone_name = entry.findtext('zoneName', '')
            record_name = entry.findtext('recordName', '')
            record_type = entry.findtext('recordType', 'A')
            current_status = entry.findtext('status', 'pending')

            if not zone_id or not record_name:
                continue

            # Get account/token for this entry
            account = accounts.get(account_uuid)
            if not account or not account['token']:
                result['errors'].append({
                    'uuid': entry_uuid,
                    'error': f'No valid account/token for entry {record_name}.{zone_name}'
                })
                continue

            # Get or create API instance for this account
            if account_uuid not in api_cache:
                api_cache[account_uuid] = HCloudAPI(account['token'], api_type=account['apiType'])
            api = api_cache[account_uuid]

            # Cache key includes account to handle different tokens
            cache_key = f"{account_uuid}:{zone_id}"

            # Get records for this zone (cached)
            if cache_key not in zone_records_cache:
                try:
                    zone_records_cache[cache_key] = api.list_records(zone_id)
                except Exception as e:
                    result['errors'].append({
                        'uuid': entry_uuid,
                        'error': f'Failed to get records for zone {zone_name}: {str(e)}'
                    })
                    zone_records_cache[cache_key] = []

            # Find matching record
            hetzner_ip = None
            record_id = None
            for record in zone_records_cache[cache_key]:
                if record.get('name') == record_name and record.get('type') == record_type:
                    hetzner_ip = record.get('value')
                    record_id = record.get('id')
                    break

            entry_status = {
                'uuid': entry_uuid,
                'zoneName': zone_name,
                'recordName': record_name,
                'recordType': record_type,
                'hetznerIp': hetzner_ip,
                'recordId': record_id,
                'configStatus': current_status
            }

            if hetzner_ip:
                entry_status['status'] = 'found'
            else:
                entry_status['status'] = 'not_found'

            result['entries'].append(entry_status)

    except ET.ParseError as e:
        return {'status': 'error', 'message': f'Config parse error: {str(e)}'}
    except Exception as e:
        return {'status': 'error', 'message': str(e)}

    return result


def main():
    result = refresh_status()
    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
