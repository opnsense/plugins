#!/usr/local/bin/python3

"""
Return the last N lines of the xproxy log file as JSON.
Uses bounded memory (deque) instead of loading the entire file.
Usage: show_log.py [lines]
"""

import sys
import os
from collections import deque

LOG_FILE = '/var/log/xproxy.log'
DEFAULT_LINES = 200
MAX_LINES = 10000


def tail(filepath, n):
    if not os.path.exists(filepath):
        return ''
    n = max(1, min(int(n), MAX_LINES))
    try:
        with open(filepath, 'r', errors='replace') as f:
            return ''.join(deque(f, maxlen=n))
    except OSError:
        return ''


def main():
    n = DEFAULT_LINES
    if len(sys.argv) > 1:
        try:
            n = int(sys.argv[1])
        except ValueError:
            pass
    print(tail(LOG_FILE, n), end='')


if __name__ == '__main__':
    main()
