#!/usr/local/bin/python3
"""
Send a Telegram notification.
Resolves api.telegram.org via an explicit DNS server (drill) and uses
curl --resolve to bypass the system DNS resolver entirely.

Usage: send_message.py <event_type> <base64_encoded_message>
"""

import sys
import json
import base64
import subprocess
import re
import xml.etree.ElementTree as ET
import urllib.parse
import shutil

CONFIG_PATH = '/conf/config.xml'
TELEGRAM_HOST = 'api.telegram.org'
TELEGRAM_PORT = 443

EVENT_FIELDS = {
    'system':   'eventSystem',
    'gateway':  'eventGateway',
    'service':  'eventService',
    'vpn':      'eventVpn',
    'security': 'eventSecurity',
    'updates':  'eventUpdates',
}

EVENT_LABELS = {
    'system':   'System',
    'gateway':  'Gateway',
    'service':  'Service',
    'vpn':      'VPN',
    'security': 'Security',
    'updates':  'Updates',
}


def read_config():
    root = ET.parse(CONFIG_PATH).getroot()
    node = root.find('.//OPNsense/TelegramNotify/general')
    if node is None:
        raise RuntimeError('TelegramNotify configuration node not found in config.xml')
    keys = [
        'botToken', 'chatId', 'threadId', 'parseMode',
        'disableWebPagePreview', 'disableNotification', 'dnsServer',
        'eventSystem', 'eventGateway', 'eventService',
        'eventVpn', 'eventSecurity', 'eventUpdates',
    ]
    return {k: (node.findtext(k) or '').strip() for k in keys}


def resolve_via_drill(hostname, dns_server):
    """Return (ipv4, error). Uses an explicit DNS server and never falls back silently."""
    drill_bin = None
    for candidate in ('/usr/bin/drill', '/usr/local/bin/drill', 'drill'):
        found = shutil.which(candidate)
        if found:
            drill_bin = found
            break

    if not drill_bin:
        return None, 'drill binary not found on system'

    try:
        result = subprocess.run(
            [drill_bin, '@' + dns_server, hostname, 'A'],
            capture_output=True, text=True, timeout=8
        )
    except Exception as e:
        return None, 'drill execution failed: ' + str(e)

    for line in result.stdout.splitlines():
        parts = line.strip().split()
        # drill answer section: name ttl class type address
        if len(parts) == 5 and parts[3] == 'A':
            ip = parts[4]
            if re.match(r'^\d{1,3}(\.\d{1,3}){3}$', ip):
                return ip, None

    detail = (result.stderr or result.stdout or '').strip()
    if detail:
        detail = detail[:200]
    else:
        detail = 'no A record returned'
    return None, detail


def send_via_curl(token, data, resolved_ip=None):
    """Call the Telegram sendMessage endpoint via the system curl binary."""
    url = 'https://{}/bot{}/sendMessage'.format(TELEGRAM_HOST, token)
    body = urllib.parse.urlencode(data)

    cmd = [
        '/usr/local/bin/curl',
        '-s',
        '-S',
        '--max-time', '20',
        '--connect-timeout', '10',
        '-4',           # force IPv4
        '-X', 'POST',
        '-d', body,
    ]

    if resolved_ip:
        # Inject resolved IP so curl never needs DNS
        cmd += ['--resolve', '{}:{}:{}'.format(TELEGRAM_HOST, TELEGRAM_PORT, resolved_ip)]

    cmd.append(url)

    result = subprocess.run(cmd, capture_output=True, text=True, timeout=25)
    return result.stdout, result.stderr, result.returncode


def main():
    if len(sys.argv) < 3:
        out({'ok': False, 'description': 'Usage: send_message.py <event_type> <b64_message>'})
        sys.exit(1)

    event_type = sys.argv[1].lower().strip()

    try:
        message = base64.b64decode(sys.argv[2]).decode('utf-8')
    except Exception:
        out({'ok': False, 'description': 'Failed to decode message argument'})
        sys.exit(1)

    if event_type not in EVENT_FIELDS:
        out({'ok': False, 'description': 'Invalid event type: ' + event_type})
        sys.exit(1)

    try:
        cfg = read_config()
    except Exception as e:
        out({'ok': False, 'description': 'Config read error: ' + str(e)})
        sys.exit(1)

    token = cfg.get('botToken', '')
    chat_id = cfg.get('chatId', '')

    if not token or not chat_id:
        out({'ok': False, 'description': 'Bot token and Chat ID are required'})
        sys.exit(1)

    if cfg.get(EVENT_FIELDS[event_type], '1') == '0':
        out({'ok': False, 'description': 'Event type {} is disabled in settings'.format(event_type)})
        sys.exit(1)

    full_message = '[{}] {}'.format(EVENT_LABELS[event_type], message)

    # Resolve hostname bypassing system DNS
    dns_server = cfg.get('dnsServer', '') or '8.8.8.8'
    resolved_ip, resolve_error = resolve_via_drill(TELEGRAM_HOST, dns_server)
    if not resolved_ip:
        out({'ok': False, 'description': 'DNS resolve failed via {} using {}: {}'.format(dns_server, 'drill', resolve_error)})
        sys.exit(1)

    post_data = {
        'chat_id': chat_id,
        'text': full_message,
    }
    if cfg.get('disableWebPagePreview') == '1':
        post_data['disable_web_page_preview'] = 'true'
    if cfg.get('disableNotification') == '1':
        post_data['disable_notification'] = 'true'
    if cfg.get('threadId'):
        post_data['message_thread_id'] = cfg['threadId']
    parse_mode = cfg.get('parseMode', '')
    if parse_mode and parse_mode != 'None':
        post_data['parse_mode'] = parse_mode

    try:
        stdout, stderr, returncode = send_via_curl(token, post_data, resolved_ip)
        if returncode != 0:
            err = (stderr or stdout or '').strip()
            out({'ok': False, 'description': 'curl failed: ' + err[:200]})
            sys.exit(1)
        response = json.loads(stdout)
    except json.JSONDecodeError:
        out({'ok': False, 'description': 'curl returned non-JSON: ' + stdout[:200]})
        sys.exit(1)
    except subprocess.TimeoutExpired:
        out({'ok': False, 'description': 'curl request timed out'})
        sys.exit(1)
    except Exception as e:
        out({'ok': False, 'description': 'Unexpected error: ' + str(e)})
        sys.exit(1)

    out(response)


def out(obj):
    print(json.dumps(obj))


if __name__ == '__main__':
    main()
