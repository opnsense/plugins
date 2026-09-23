#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Manage DNS change history (cleanup, clear, revert, get) for HCloudDNS
"""

import fcntl
import json
import os
import sys
import time

HISTORY_FILE = '/var/log/hclouddns/history.jsonl'


def _read_entries():
    """Read all JSONL entries"""
    entries = []
    if not os.path.exists(HISTORY_FILE):
        return entries
    with open(HISTORY_FILE, 'r') as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                entries.append(json.loads(line))
            except json.JSONDecodeError:
                continue
    return entries


def _write_entries(entries):
    """Write entries back to JSONL with locking"""
    fd = os.open(HISTORY_FILE, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    try:
        with os.fdopen(fd, 'w') as f:
            fcntl.flock(f, fcntl.LOCK_EX)
            try:
                for entry in entries:
                    f.write(json.dumps(entry) + '\n')
            finally:
                fcntl.flock(f, fcntl.LOCK_UN)
    except Exception:
        try:
            os.close(fd)
        except OSError:
            pass
        raise


def cleanup(days):
    """Remove entries older than N days"""
    days = int(days)
    if days <= 0:
        return {'status': 'ok', 'deleted': 0, 'message': 'No cleanup needed'}

    cutoff = int(time.time()) - (days * 86400)
    entries = _read_entries()
    kept = [e for e in entries if e.get('timestamp', 0) >= cutoff]
    deleted = len(entries) - len(kept)

    if deleted > 0:
        _write_entries(kept)

    return {
        'status': 'ok',
        'deleted': deleted,
        'message': f'Cleaned up {deleted} old history entries'
    }


def clear():
    """Remove all history entries"""
    entries = _read_entries()
    deleted = len(entries)

    if deleted > 0:
        _write_entries([])

    return {
        'status': 'ok',
        'deleted': deleted,
        'message': f'Cleared all {deleted} history entries'
    }


def revert(uuid):
    """Mark an entry as reverted (actual DNS revert is done by PHP controller)"""
    entries = _read_entries()

    for entry in entries:
        if entry.get('uuid') == uuid:
            if entry.get('reverted'):
                return {'status': 'error', 'message': 'Already reverted'}
            entry['reverted'] = True
            _write_entries(entries)
            return {'status': 'ok', 'message': 'Entry marked as reverted'}

    return {'status': 'error', 'message': 'Entry not found'}


def get(uuid):
    """Get a single history entry by UUID"""
    entries = _read_entries()

    for entry in entries:
        if entry.get('uuid') == uuid:
            ts = entry.get('timestamp', 0)
            entry['timestampFormatted'] = time.strftime(
                '%Y-%m-%d %H:%M:%S', time.localtime(ts)
            )
            entry['reverted'] = '1' if entry.get('reverted') else '0'
            return {'status': 'ok', 'change': entry}

    return {'status': 'error', 'message': 'Entry not found'}


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'status': 'error', 'message': 'Usage: manage_history.py <action> [args]'}))
        sys.exit(1)

    action = sys.argv[1]

    if action == 'cleanup':
        days = sys.argv[2] if len(sys.argv) > 2 else '7'
        result = cleanup(days)
    elif action == 'clear':
        result = clear()
    elif action == 'revert':
        if len(sys.argv) < 3:
            result = {'status': 'error', 'message': 'UUID required'}
        else:
            result = revert(sys.argv[2])
    elif action == 'get':
        if len(sys.argv) < 3:
            result = {'status': 'error', 'message': 'UUID required'}
        else:
            result = get(sys.argv[2])
    else:
        result = {'status': 'error', 'message': f'Unknown action: {action}'}

    print(json.dumps(result))


if __name__ == '__main__':
    main()
