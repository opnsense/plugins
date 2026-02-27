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


def read_stats(days=30):
    """Aggregate statistics from history entries."""
    rows = []

    if not os.path.exists(HISTORY_FILE):
        return {
            'status': 'ok',
            'total': 0, 'creates': 0, 'updates': 0, 'deletes': 0,
            'reverted': 0,
            'byDate': {}, 'byZone': {}, 'byAccount': {},
            'avgPerDay': 0
        }

    cutoff = time.time() - (days * 86400)

    try:
        with open(HISTORY_FILE, 'r') as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                try:
                    entry = json.loads(line)
                    rows.append(entry)
                except json.JSONDecodeError:
                    continue
    except IOError:
        pass

    # Filter by time range
    filtered = [r for r in rows if r.get('timestamp', 0) >= cutoff]

    total = len(filtered)
    creates = sum(1 for r in filtered if r.get('action') == 'create')
    updates = sum(1 for r in filtered if r.get('action') == 'update')
    deletes = sum(1 for r in filtered if r.get('action') == 'delete')
    reverted = sum(1 for r in filtered if r.get('reverted'))

    # Group by date
    by_date = {}
    for r in filtered:
        date_str = time.strftime('%Y-%m-%d', time.localtime(r.get('timestamp', 0)))
        if date_str not in by_date:
            by_date[date_str] = {'create': 0, 'update': 0, 'delete': 0}
        action = r.get('action', '')
        if action in by_date[date_str]:
            by_date[date_str][action] += 1

    # Group by zone
    by_zone = {}
    for r in filtered:
        zone = r.get('zoneName', 'Unknown')
        by_zone[zone] = by_zone.get(zone, 0) + 1

    # Group by account
    by_account = {}
    for r in filtered:
        account = r.get('accountName', 'Unknown')
        by_account[account] = by_account.get(account, 0) + 1

    # Avg per day
    unique_days = len(by_date) if by_date else 1
    avg_per_day = round(total / unique_days, 1)

    return {
        'status': 'ok',
        'total': total,
        'creates': creates,
        'updates': updates,
        'deletes': deletes,
        'reverted': reverted,
        'byDate': by_date,
        'byZone': by_zone,
        'byAccount': by_account,
        'avgPerDay': avg_per_day
    }


def main():
    # Check for stats mode
    if len(sys.argv) > 1 and sys.argv[1] == 'stats':
        days = int(sys.argv[2]) if len(sys.argv) > 2 else 30
        result = read_stats(days)
    else:
        result = read_history()
    print(json.dumps(result))


if __name__ == '__main__':
    main()
