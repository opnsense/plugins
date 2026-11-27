#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    List DNS records for a zone
"""
import sys
import json
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI

# All supported record types
ALL_RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR', 'SOA']


def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: list_records.py <token> <zone_id> [all]',
            'records': []
        }))
        sys.exit(1)

    token = sys.argv[1].strip()
    zone_id = sys.argv[2].strip()
    # Optional third arg: 'all' to list all record types
    list_all = len(sys.argv) > 3 and sys.argv[3].strip().lower() == 'all'

    if not token or not zone_id:
        print(json.dumps({
            'status': 'error',
            'message': 'Token and zone_id are required',
            'records': []
        }))
        sys.exit(1)

    api = HCloudAPI(token)

    # List all record types or just A/AAAA
    record_types = ALL_RECORD_TYPES if list_all else ['A', 'AAAA']
    records = api.list_records(zone_id, record_types)

    # Sort records: first by type priority, then by name
    type_order = {t: i for i, t in enumerate(ALL_RECORD_TYPES)}
    records.sort(key=lambda r: (type_order.get(r['type'], 99), r['name']))

    result = {
        'status': 'ok' if records is not None else 'error',
        'message': f'Found {len(records)} record(s)' if records else 'No records found or API error',
        'records': records if records else []
    }

    print(json.dumps(result))
    sys.exit(0)


if __name__ == '__main__':
    main()
