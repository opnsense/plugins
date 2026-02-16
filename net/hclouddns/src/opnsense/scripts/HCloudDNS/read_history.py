#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Read DNS change history from JSONL file for HCloudDNS
"""

import json
import os
import sys
import time

HISTORY_FILE = '/var/log/hclouddns/history.jsonl'


def read_history():
    """Read all history entries from JSONL file, return newest-first"""
    rows = []

    if not os.path.exists(HISTORY_FILE):
        return {'status': 'ok', 'rows': [], 'rowCount': 0, 'total': 0, 'current': 1}

    try:
        with open(HISTORY_FILE, 'r') as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                try:
                    entry = json.loads(line)
                    # Add formatted timestamp
                    ts = entry.get('timestamp', 0)
                    entry['timestampFormatted'] = time.strftime(
                        '%Y-%m-%d %H:%M:%S', time.localtime(ts)
                    )
                    # Normalize reverted to string for PHP compatibility
                    entry['reverted'] = '1' if entry.get('reverted') else '0'
                    rows.append(entry)
                except json.JSONDecodeError:
                    continue
    except IOError:
        pass

    # Sort newest first
    rows.sort(key=lambda x: x.get('timestamp', 0), reverse=True)

    return {
        'status': 'ok',
        'rows': rows,
        'rowCount': len(rows),
        'total': len(rows),
        'current': 1
    }


def main():
    result = read_history()
    print(json.dumps(result))


if __name__ == '__main__':
    main()
