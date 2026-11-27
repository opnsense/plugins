#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Delete a DNS record at Hetzner
"""
import sys
import json
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def main():
    # Expected args: token zone_id record_name record_type
    if len(sys.argv) < 5:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: delete_record.py <token> <zone_id> <name> <type>'
        }))
        sys.exit(1)

    token = sys.argv[1].strip()
    zone_id = sys.argv[2].strip()
    record_name = sys.argv[3].strip()
    record_type = sys.argv[4].strip().upper()

    if not all([token, zone_id, record_name, record_type]):
        print(json.dumps({
            'status': 'error',
            'message': 'Missing required parameters'
        }))
        sys.exit(1)

    api = HCloudAPI(token)

    try:
        success, message = api.delete_record(zone_id, record_name, record_type)
        if success:
            print(json.dumps({
                'status': 'ok',
                'message': f'Record {record_name} ({record_type}) deleted successfully'
            }))
            sys.exit(0)
        else:
            print(json.dumps({
                'status': 'error',
                'message': f'Failed to delete record: {message}'
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
