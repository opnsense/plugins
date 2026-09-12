#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Create a new DNS zone at Hetzner
"""
import sys
import json
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def main():
    # Expected args: token zone_name
    if len(sys.argv) < 3:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: create_zone.py <token> <zone_name>'
        }))
        sys.exit(1)

    token = sys.argv[1].strip()
    zone_name = sys.argv[2].strip().lower()

    if not all([token, zone_name]):
        print(json.dumps({
            'status': 'error',
            'message': 'Missing required parameters'
        }))
        sys.exit(1)

    # Basic domain name validation
    if not all(c.isalnum() or c in '.-' for c in zone_name) or '.' not in zone_name:
        print(json.dumps({
            'status': 'error',
            'message': f'Invalid zone name: {zone_name}'
        }))
        sys.exit(1)

    api = HCloudAPI(token)

    try:
        success, message, zone_id = api.create_zone(zone_name)
        if success:
            print(json.dumps({
                'status': 'ok',
                'message': f'Zone {zone_name} created successfully',
                'zone_id': zone_id,
                'zone_name': zone_name
            }))
            sys.exit(0)
        else:
            print(json.dumps({
                'status': 'error',
                'message': f'Failed to create zone: {message}'
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
