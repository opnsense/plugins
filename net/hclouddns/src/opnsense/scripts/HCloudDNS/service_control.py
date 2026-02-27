#!/usr/local/bin/python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Service control for HCloudDNS - handles start/stop via configd
"""
import sys
import json
import os
import syslog

STOPPED_FLAG = '/var/run/hclouddns.stopped'


def log(message, priority=syslog.LOG_INFO):
    syslog.openlog('hclouddns', syslog.LOG_PID, syslog.LOG_LOCAL4)
    syslog.syslog(priority, message)


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else 'help'

    if action == 'stop':
        log('Service stop requested')
        try:
            fd = os.open(STOPPED_FLAG, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
            os.close(fd)
        except OSError:
            pass
        print(json.dumps({'status': 'ok', 'message': 'HCloudDNS service stopped'}))
    elif action == 'start':
        log('Service start requested')
        try:
            os.unlink(STOPPED_FLAG)
        except FileNotFoundError:
            pass
        print(json.dumps({'status': 'ok', 'message': 'HCloudDNS service started'}))
    else:
        print(json.dumps({'status': 'error', 'message': f'Unknown action: {action}'}))


if __name__ == '__main__':
    main()
