#!/usr/local/bin/python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Export DNS zone in BIND format for HCloudDNS.
"""

import json
import sys
import os
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI

ALL_RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'SOA']


def export_zone(token, zone_id):
    """Export zone as BIND-format zonefile."""
    api = HCloudAPI(token)

    zones = api.list_zones()
    zone_name = zone_id
    for z in zones:
        if z.get('id') == zone_id:
            zone_name = z.get('name', zone_id)
            break

    records = api.list_records(zone_id, ALL_RECORD_TYPES)

    lines = []
    lines.append(f'; Zone: {zone_name}')
    lines.append(f'; Exported: {time.strftime("%Y-%m-%d %H:%M:%S")}')
    lines.append(f'; Records: {len(records)}')
    lines.append(f'$ORIGIN {zone_name}.')
    lines.append('')

    # Sort records: SOA first, then NS, then by type and name
    type_order = {'SOA': 0, 'NS': 1, 'A': 2, 'AAAA': 3, 'CNAME': 4, 'MX': 5, 'TXT': 6, 'SRV': 7, 'CAA': 8}

    records.sort(key=lambda r: (
        type_order.get(r.get('type', ''), 99),
        r.get('name', '')
    ))

    for rec in records:
        name = rec.get('name', '@')
        rtype = rec.get('type', 'A')
        value = rec.get('value', '')
        ttl = rec.get('ttl', 300)

        # Format name: pad to 16 chars
        display_name = name if name != '@' else '@'
        display_name = display_name.ljust(16)

        # Format value based on type
        if rtype == 'TXT':
            # Ensure TXT values are quoted
            if not value.startswith('"'):
                value = f'"{value}"'
        elif rtype == 'CNAME' or rtype == 'NS' or rtype == 'MX':
            # Add trailing dot if not present
            if value and not value.endswith('.') and not value.endswith('. '):
                # For MX: priority hostname.
                parts = value.split()
                if rtype == 'MX' and len(parts) == 2:
                    if not parts[1].endswith('.'):
                        value = f'{parts[0]} {parts[1]}.'
                elif rtype != 'MX' and not value.endswith('.'):
                    value = value + '.'

        lines.append(f'{display_name} {ttl}\tIN\t{rtype}\t{value}')

    content = '\n'.join(lines) + '\n'
    filename = f'{zone_name}.zone'

    return {
        'status': 'ok',
        'content': content,
        'filename': filename,
        'zone': zone_name,
        'recordCount': len(records)
    }


def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: zone_export.py <token> <zone_id>'
        }))
        sys.exit(1)

    token = sys.argv[1].strip()
    zone_id = sys.argv[2].strip()

    result = export_zone(token, zone_id)
    print(json.dumps(result))


if __name__ == '__main__':
    main()
