#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    List DNS zones for Hetzner Cloud API token
"""
import sys
import json
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def main():
    token = None

    if len(sys.argv) > 1:
        token = sys.argv[1].strip()
    else:
        try:
            token = sys.stdin.read().strip()
        except Exception:
            pass

    if not token:
        print(json.dumps({
            'status': 'error',
            'message': 'No API token provided',
            'zones': []
        }))
        sys.exit(1)

    api = HCloudAPI(token)
    zones = api.list_zones()

    result = {
        'status': 'ok' if zones else 'error',
        'message': f'Found {len(zones)} zone(s)' if zones else 'No zones found or API error',
        'zones': zones
    }

    print(json.dumps(result))
    sys.exit(0 if zones else 1)


if __name__ == '__main__':
    main()
