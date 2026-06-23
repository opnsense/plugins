#!/usr/local/bin/python3
"""
Poll Monit log entries and forward alert-like lines to Telegram Notify.

Usage:
  monit_poll.py [max_messages]

Designed for periodic execution from configd/cron. Keeps a file offset state to
only process new log lines each run.
"""

import base64
import json
import os
import subprocess
import sys

MONIT_LOG = '/var/log/monit.log'
STATE_FILE = '/var/db/telegramnotify_monit_state.json'
PYTHON_BIN = '/usr/local/bin/python3'
SEND_SCRIPT = '/usr/local/opnsense/scripts/OPNsense/TelegramNotify/send_message.py'

KEYWORDS = (
    'failed',
    'failure',
    'timeout',
    'error',
    'does not exist',
    'not running',
    'connection failed',
    'execution failed',
)


def out(payload):
    print(json.dumps(payload))


def load_state():
    try:
        with open(STATE_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
            if isinstance(data, dict):
                return data
    except Exception:
        pass
    return {'offset': 0}


def save_state(offset):
    try:
        os.makedirs(os.path.dirname(STATE_FILE), exist_ok=True)
        with open(STATE_FILE, 'w', encoding='utf-8') as f:
            json.dump({'offset': int(offset)}, f)
    except Exception:
        pass


def is_alert_line(line):
    lline = line.lower()
    return any(keyword in lline for keyword in KEYWORDS)


def send_message(text):
    msg_b64 = base64.b64encode(text.encode('utf-8')).decode('ascii')
    result = subprocess.run(
        [PYTHON_BIN, SEND_SCRIPT, 'service', msg_b64],
        capture_output=True,
        text=True,
        timeout=30,
    )
    if result.returncode != 0:
        err = (result.stderr or result.stdout or 'unknown error').strip()
        return False, err[:250]

    output = (result.stdout or '').strip()
    try:
        data = json.loads(output)
    except Exception:
        return False, 'send_message returned invalid JSON'

    if data.get('ok'):
        return True, None

    return False, str(data.get('description') or 'unknown Telegram API error')


def main():
    max_messages = 3
    if len(sys.argv) > 1:
        try:
            max_messages = max(1, min(20, int(sys.argv[1])))
        except Exception:
            max_messages = 3

    if not os.path.exists(MONIT_LOG):
        out({'status': 'ok', 'processed': 0, 'sent': 0, 'message': 'Monit log not found'})
        return

    state = load_state()
    start_offset = int(state.get('offset') or 0)
    processed = 0
    sent = 0
    errors = []

    try:
        with open(MONIT_LOG, 'r', encoding='utf-8', errors='ignore') as f:
            file_size = os.path.getsize(MONIT_LOG)
            if start_offset < 0 or start_offset > file_size:
                start_offset = 0
            f.seek(start_offset)

            while sent < max_messages:
                line = f.readline()
                if not line:
                    break

                processed += 1
                line = line.strip()
                if not line:
                    continue
                if not is_alert_line(line):
                    continue

                msg = 'Monit alert\n{}'.format(line)
                ok, err = send_message(msg)
                if ok:
                    sent += 1
                else:
                    errors.append(err)

            end_offset = f.tell()

        save_state(end_offset)

    except Exception as e:
        out({'status': 'failed', 'processed': processed, 'sent': sent, 'message': str(e)})
        sys.exit(1)

    result = {'status': 'ok', 'processed': processed, 'sent': sent}
    if errors:
        result['errors'] = errors[:3]
    out(result)


if __name__ == '__main__':
    main()
