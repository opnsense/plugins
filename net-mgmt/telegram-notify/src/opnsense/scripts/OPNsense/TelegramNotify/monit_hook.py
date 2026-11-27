#!/usr/local/bin/python3
"""
Bridge Monit alert events to Telegram Notify.

Intended usage from Monit exec hooks through configctl:
  configctl telegramnotify monit "$SERVICE" "$EVENT" "$ACTION" "$DESCRIPTION" "$HOST" "$DATE"
"""

import base64
import json
import subprocess
import sys

SEND_SCRIPT = '/usr/local/opnsense/scripts/OPNsense/TelegramNotify/send_message.py'
PYTHON_BIN = '/usr/local/bin/python3'


def json_out(payload):
    print(json.dumps(payload))


def safe_value(index):
    if len(sys.argv) > index:
        value = (sys.argv[index] or '').strip()
        return value if value else 'n/a'
    return 'n/a'


def main():
    service = safe_value(1)
    event = safe_value(2)
    action = safe_value(3)
    description = safe_value(4)
    host = safe_value(5)
    date = safe_value(6)

    msg = (
        'Monit alert\n'
        'Service: {}\n'
        'Event: {}\n'
        'Action: {}\n'
        'Host: {}\n'
        'Date: {}\n'
        'Description: {}'
    ).format(service, event, action, host, date, description)

    msg_b64 = base64.b64encode(msg.encode('utf-8')).decode('ascii')

    try:
        result = subprocess.run(
            [PYTHON_BIN, SEND_SCRIPT, 'service', msg_b64],
            capture_output=True,
            text=True,
            timeout=30
        )
    except Exception as e:
        json_out({'ok': False, 'description': 'monit_hook execution failed: ' + str(e)})
        sys.exit(1)

    output = (result.stdout or '').strip()
    if result.returncode != 0:
        err = (result.stderr or output or 'unknown error').strip()
        json_out({'ok': False, 'description': 'send_message failed: ' + err[:250]})
        sys.exit(1)

    if output:
        print(output)
        return

    json_out({'ok': False, 'description': 'send_message returned no output'})
    sys.exit(1)


if __name__ == '__main__':
    main()
