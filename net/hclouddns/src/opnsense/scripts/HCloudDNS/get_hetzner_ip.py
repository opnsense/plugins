#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Get current IP from Hetzner DNS for a specific record
"""

import json
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI


def get_hetzner_ip(zone_id, record_name, record_type):
    """Get current IP for a record from Hetzner DNS"""
    # Read API token from config
    try:
        import xml.etree.ElementTree as ET
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()
        token_node = root.find('.//OPNsense/HCloudDNS/apiToken')
        if token_node is None or not token_node.text:
            return {'status': 'error', 'message': 'No API token configured'}
        token = token_node.text
    except Exception as e:
        return {'status': 'error', 'message': f'Config error: {str(e)}'}

    api = HCloudAPI(token)

    try:
        records = api.list_records(zone_id)
        for record in records:
            if record.get('name') == record_name and record.get('type') == record_type:
                return {
                    'status': 'ok',
                    'ip': record.get('value'),
                    'recordId': record.get('id'),
                    'ttl': record.get('ttl'),
                    'modified': record.get('modified')
                }

        return {'status': 'error', 'message': 'Record not found'}
    except Exception as e:
        return {'status': 'error', 'message': str(e)}


def main():
    if len(sys.argv) < 4:
        print(json.dumps({'status': 'error', 'message': 'Usage: get_hetzner_ip.py <zone_id> <record_name> <record_type>'}))
        sys.exit(1)

    zone_id = sys.argv[1]
    record_name = sys.argv[2]
    record_type = sys.argv[3]

    result = get_hetzner_ip(zone_id, record_name, record_type)
    print(json.dumps(result))


if __name__ == '__main__':
    main()
