#!/usr/local/bin/python3
"""
Poll Suricata EVE alerts and forward them to Telegram Notify.

Usage:
  ids_poll.py [max_alerts]

Designed for periodic execution from configd/cron. Keeps a file offset state to
only process new alerts each run.
"""

import base64
import json
import os
import subprocess
import sys

EVE_FILE = '/var/log/suricata/eve.json'
STATE_FILE = '/var/db/telegramnotify_ids_state.json'
PYTHON_BIN = '/usr/local/bin/python3'
SEND_SCRIPT = '/usr/local/opnsense/scripts/OPNsense/TelegramNotify/send_message.py'


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
        directory = os.path.dirname(STATE_FILE)
        os.makedirs(directory, exist_ok=True)
        with open(STATE_FILE, 'w', encoding='utf-8') as f:
            json.dump({'offset': int(offset)}, f)
    except Exception:
        # Non-fatal: next run may resend some alerts.
        pass


def format_alert(evt):
    alert = evt.get('alert') or {}
    signature = str(alert.get('signature') or 'unknown signature')
    category = str(alert.get('category') or 'unknown category')
    severity = str(alert.get('severity') or 'n/a')
    action = str(alert.get('action') or evt.get('action') or 'n/a')
    src_ip = str(evt.get('src_ip') or 'n/a')
    src_port = str(evt.get('src_port') or 'n/a')
    dst_ip = str(evt.get('dest_ip') or 'n/a')
    dst_port = str(evt.get('dest_port') or 'n/a')
    proto = str(evt.get('proto') or 'n/a')
    iface = str(evt.get('in_iface') or evt.get('interface') or 'n/a')

    return (
        'IDS alert\n'
        'Signature: {}\n'
        'Category: {}\n'
        'Severity: {}\n'
        'Action: {}\n'
        'Proto: {}\n'
        'From: {}:{}\n'
        'To: {}:{}\n'
        'Interface: {}'
    ).format(signature, category, severity, action, proto, src_ip, src_port, dst_ip, dst_port, iface)


def is_blocked_event(evt):
    event_type = str(evt.get('event_type') or '').lower()
    if event_type == 'drop':
        return True

    alert = evt.get('alert') or {}
    action = str(alert.get('action') or evt.get('action') or '').lower()
    return action in ('blocked', 'drop', 'dropped', 'reject', 'rejected')


def send_message(text):
    msg_b64 = base64.b64encode(text.encode('utf-8')).decode('ascii')
    result = subprocess.run(
        [PYTHON_BIN, SEND_SCRIPT, 'security', msg_b64],
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
    max_alerts = 5
    if len(sys.argv) > 1:
        try:
            max_alerts = max(1, min(50, int(sys.argv[1])))
        except Exception:
            max_alerts = 5

    if not os.path.exists(EVE_FILE):
        out({'status': 'ok', 'processed': 0, 'sent': 0, 'message': 'EVE log not found'})
        return

    state = load_state()
    start_offset = int(state.get('offset') or 0)
    processed = 0
    sent = 0
    errors = []

    try:
        with open(EVE_FILE, 'r', encoding='utf-8', errors='ignore') as f:
            file_size = os.path.getsize(EVE_FILE)
            if start_offset < 0 or start_offset > file_size:
                start_offset = 0
            f.seek(start_offset)

            while sent < max_alerts:
                line = f.readline()
                if not line:
                    break

                processed += 1
                line = line.strip()
                if not line:
                    continue

                try:
                    evt = json.loads(line)
                except Exception:
                    continue

                if str(evt.get('event_type')) not in ('alert', 'drop'):
                    continue

                if not is_blocked_event(evt):
                    continue

                msg = format_alert(evt)
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

    response = {'status': 'ok', 'processed': processed, 'sent': sent}
    if errors:
        response['errors'] = errors[:3]
    out(response)


if __name__ == '__main__':
    main()
