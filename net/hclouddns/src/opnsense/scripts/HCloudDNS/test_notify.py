#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Test notification channels for HCloudDNS
"""
import json
import subprocess
import urllib.request
import urllib.error
from xml.etree import ElementTree

CONFIG_PATH = '/conf/config.xml'


def get_notification_settings():
    """Read notification settings from OPNsense config.xml"""
    try:
        tree = ElementTree.parse(CONFIG_PATH)
        root = tree.getroot()

        hcloud = root.find('.//OPNsense/HCloudDNS')
        if hcloud is None:
            return None

        notifications = hcloud.find('notifications')
        if notifications is None:
            return None

        return {
            'enabled': notifications.findtext('enabled', '0') == '1',
            'emailEnabled': notifications.findtext('emailEnabled', '0') == '1',
            'emailTo': notifications.findtext('emailTo', ''),
            'webhookEnabled': notifications.findtext('webhookEnabled', '0') == '1',
            'webhookUrl': notifications.findtext('webhookUrl', ''),
            'webhookMethod': notifications.findtext('webhookMethod', 'POST'),
            'ntfyEnabled': notifications.findtext('ntfyEnabled', '0') == '1',
            'ntfyServer': notifications.findtext('ntfyServer', 'https://ntfy.sh'),
            'ntfyTopic': notifications.findtext('ntfyTopic', ''),
            'ntfyPriority': notifications.findtext('ntfyPriority', 'default'),
        }
    except Exception:
        return None


def send_email(to_address):
    """Send test email using OPNsense mail system"""
    try:
        subject = "HCloudDNS Test Notification"
        body = "This is a test notification from HCloudDNS plugin.\n\nIf you received this, email notifications are working correctly."

        # Use OPNsense's mail command
        result = subprocess.run(
            ['/usr/local/bin/mail', '-s', subject, to_address],
            input=body.encode(),
            capture_output=True,
            timeout=30
        )

        if result.returncode == 0:
            return {'success': True, 'message': f'Sent to {to_address}'}
        else:
            return {'success': False, 'message': result.stderr.decode()[:100]}
    except subprocess.TimeoutExpired:
        return {'success': False, 'message': 'Timeout sending email'}
    except Exception as e:
        return {'success': False, 'message': str(e)[:100]}


def send_webhook(url, method):
    """Send test webhook notification"""
    try:
        payload = {
            'event': 'test',
            'message': 'This is a test notification from HCloudDNS plugin',
            'timestamp': __import__('time').time(),
            'plugin': 'os-hclouddns'
        }

        data = json.dumps(payload).encode('utf-8')
        headers = {'Content-Type': 'application/json'}

        if method == 'GET':
            # For GET, append as query params
            import urllib.parse
            params = urllib.parse.urlencode({'event': 'test', 'message': 'HCloudDNS test'})
            url = f"{url}?{params}" if '?' not in url else f"{url}&{params}"
            req = urllib.request.Request(url, headers=headers, method='GET')
        else:
            req = urllib.request.Request(url, data=data, headers=headers, method='POST')

        with urllib.request.urlopen(req, timeout=10) as response:
            return {'success': True, 'message': f'HTTP {response.status}'}
    except urllib.error.HTTPError as e:
        return {'success': False, 'message': f'HTTP {e.code}: {e.reason}'}
    except urllib.error.URLError as e:
        return {'success': False, 'message': str(e.reason)[:100]}
    except Exception as e:
        return {'success': False, 'message': str(e)[:100]}


def send_ntfy(server, topic, priority):
    """Send test ntfy notification"""
    try:
        url = f"{server.rstrip('/')}/{topic}"

        priority_map = {
            'min': '1',
            'low': '2',
            'default': '3',
            'high': '4',
            'urgent': '5'
        }

        headers = {
            'Title': 'HCloudDNS Test',
            'Priority': priority_map.get(priority, '3'),
            'Tags': 'test,hclouddns'
        }

        message = "This is a test notification from HCloudDNS plugin."
        req = urllib.request.Request(url, data=message.encode('utf-8'), headers=headers, method='POST')

        with urllib.request.urlopen(req, timeout=10):
            return {'success': True, 'message': f'Sent to {topic}'}
    except urllib.error.HTTPError as e:
        return {'success': False, 'message': f'HTTP {e.code}: {e.reason}'}
    except urllib.error.URLError as e:
        return {'success': False, 'message': str(e.reason)[:100]}
    except Exception as e:
        return {'success': False, 'message': str(e)[:100]}


def main():
    settings = get_notification_settings()

    result = {
        'status': 'ok',
        'results': {}
    }

    if not settings:
        result['status'] = 'error'
        result['message'] = 'Could not read notification settings'
        print(json.dumps(result))
        return

    if not settings['enabled']:
        result['status'] = 'error'
        result['message'] = 'Notifications are disabled'
        print(json.dumps(result))
        return

    # Test each enabled channel
    channels_tested = 0

    if settings['emailEnabled'] and settings['emailTo']:
        result['results']['email'] = send_email(settings['emailTo'])
        channels_tested += 1

    if settings['webhookEnabled'] and settings['webhookUrl']:
        result['results']['webhook'] = send_webhook(settings['webhookUrl'], settings['webhookMethod'])
        channels_tested += 1

    if settings['ntfyEnabled'] and settings['ntfyTopic']:
        result['results']['ntfy'] = send_ntfy(settings['ntfyServer'], settings['ntfyTopic'], settings['ntfyPriority'])
        channels_tested += 1

    if channels_tested == 0:
        result['status'] = 'error'
        result['message'] = 'No notification channels configured'
    else:
        # Check if any succeeded
        successes = sum(1 for r in result['results'].values() if r.get('success'))
        if successes == 0:
            result['status'] = 'error'
            result['message'] = 'All notification tests failed'

    print(json.dumps(result))


if __name__ == '__main__':
    main()
