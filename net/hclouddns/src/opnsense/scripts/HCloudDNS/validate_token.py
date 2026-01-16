#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Validate Hetzner Cloud API token for HCloudDNS plugin
"""
import sys
import json
import os

# Add script directory to path for local imports
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def main():
    # Token passed as argument or via stdin
    token = None

    if len(sys.argv) > 1:
        token = sys.argv[1].strip()
    else:
        # Read from stdin (for security - avoids token in process list)
        try:
            token = sys.stdin.read().strip()
        except Exception:
            pass

    if not token:
        print(json.dumps({
            'valid': False,
            'message': 'No API token provided',
            'zone_count': 0
        }))
        sys.exit(1)

    api = HCloudAPI(token)
    valid, message, zone_count = api.validate_token()

    result = {
        'valid': valid,
        'message': message,
        'zone_count': zone_count
    }

    print(json.dumps(result))
    sys.exit(0 if valid else 1)


if __name__ == '__main__':
    main()
