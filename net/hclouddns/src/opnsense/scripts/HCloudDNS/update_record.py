#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Update an existing DNS record at Hetzner
"""
import sys
import json
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def main():
    # Expected args: token zone_id record_name record_type value ttl
    if len(sys.argv) < 7:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: update_record.py <token> <zone_id> <name> <type> <value> <ttl>'
        }))
        sys.exit(1)

    token = sys.argv[1].strip()
    zone_id = sys.argv[2].strip()
    record_name = sys.argv[3].strip()
    record_type = sys.argv[4].strip().upper()
    value = sys.argv[5].strip()
    ttl = int(sys.argv[6].strip()) if sys.argv[6].strip().isdigit() else 300

    if not all([token, zone_id, record_name, value]):
        print(json.dumps({
            'status': 'error',
            'message': 'Missing required parameters'
        }))
        sys.exit(1)

    # Support all common record types
    supported_types = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR', 'SOA']
    if record_type not in supported_types:
        print(json.dumps({
            'status': 'error',
            'message': f'Unsupported record type: {record_type}. Supported: {", ".join(supported_types)}'
        }))
        sys.exit(1)

    api = HCloudAPI(token)

    # TXT records need to be quoted for Hetzner API
    if record_type == 'TXT' and not value.startswith('"'):
        value = f'"{value}"'

    try:
        success, message = api.update_record(zone_id, record_name, record_type, value, ttl)
        if success:
            print(json.dumps({
                'status': 'ok',
                'message': f'Record {record_name} ({record_type}) updated successfully',
                'unchanged': message == 'unchanged'
            }))
            sys.exit(0)
        else:
            print(json.dumps({
                'status': 'error',
                'message': f'Failed to update record: {message}'
            }))
            sys.exit(1)
    except Exception as e:
        print(json.dumps({
            'status': 'error',
            'message': str(e)
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()
