#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Test notification channels for HCloudDNS
"""
import json
import sys
import smtplib
import urllib.request
import urllib.error
from email.mime.text import MIMEText
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
            'emailFrom': notifications.findtext('emailFrom', ''),
            'smtpServer': notifications.findtext('smtpServer', ''),
            'smtpPort': int(notifications.findtext('smtpPort', '587')),
            'smtpTls': notifications.findtext('smtpTls', 'starttls'),
            'smtpUser': notifications.findtext('smtpUser', ''),
            'smtpPassword': notifications.findtext('smtpPassword', ''),
            'webhookEnabled': notifications.findtext('webhookEnabled', '0') == '1',
            'webhookUrl': notifications.findtext('webhookUrl', ''),
            'webhookMethod': notifications.findtext('webhookMethod', 'POST'),
            'webhookSecret': notifications.findtext('webhookSecret', ''),
            'ntfyEnabled': notifications.findtext('ntfyEnabled', '0') == '1',
            'ntfyServer': notifications.findtext('ntfyServer', 'https://ntfy.sh'),
            'ntfyTopic': notifications.findtext('ntfyTopic', ''),
            'ntfyPriority': notifications.findtext('ntfyPriority', 'default'),
        }
    except Exception:
        return None


def send_email(settings):
    """Send test email via SMTP"""
    try:
        to_addr = settings.get('emailTo', '')
        from_addr = settings.get('emailFrom', '') or f"hclouddns@{settings.get('smtpServer', 'localhost')}"
        server = settings.get('smtpServer', '')
        port = settings.get('smtpPort', 587)
        tls_mode = settings.get('smtpTls', 'starttls')
        user = settings.get('smtpUser', '')
        password = settings.get('smtpPassword', '')

        if not server:
            return {'success': False, 'message': 'SMTP server not configured'}
        if not to_addr:
            return {'success': False, 'message': 'Recipient address not configured'}

        msg = MIMEText("This is a test notification from HCloudDNS plugin.\n\nIf you received this, email notifications are working correctly.")
        msg['Subject'] = 'HCloudDNS Test Notification'
        msg['From'] = from_addr
        msg['To'] = to_addr

        if tls_mode == 'ssl':
            smtp = smtplib.SMTP_SSL(server, port, timeout=15)
        else:
            smtp = smtplib.SMTP(server, port, timeout=15)

        try:
            if tls_mode == 'starttls':
                smtp.starttls()
            if user and password:
                smtp.login(user, password)
            smtp.sendmail(from_addr, [to_addr], msg.as_string())
            return {'success': True, 'message': f'Sent to {to_addr}'}
        finally:
            smtp.quit()
    except smtplib.SMTPAuthenticationError as e:
        return {'success': False, 'message': f'Auth failed: {str(e)[:80]}'}
    except smtplib.SMTPException as e:
        return {'success': False, 'message': f'SMTP error: {str(e)[:80]}'}
    except Exception as e:
        return {'success': False, 'message': str(e)[:100]}


def send_webhook(url, method, secret=''):
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

        if secret:
            import hmac
            import hashlib
            import time as time_mod
            timestamp = str(int(time_mod.time()))
            sig = hmac.new(
                secret.encode(),
                timestamp.encode() + b'.' + data,
                hashlib.sha256
            ).hexdigest()
            headers['X-HCloudDNS-Signature'] = sig
            headers['X-HCloudDNS-Timestamp'] = timestamp

        if method == 'GET':
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
    # Optional channel filter: email, webhook, ntfy
    channel_filter = sys.argv[1].strip() if len(sys.argv) > 1 and sys.argv[1].strip() else None

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

    channels_tested = 0

    if channel_filter is None or channel_filter == 'email':
        if settings['emailEnabled']:
            result['results']['email'] = send_email(settings)
            channels_tested += 1

    if channel_filter is None or channel_filter == 'webhook':
        if settings['webhookEnabled'] and settings['webhookUrl']:
            result['results']['webhook'] = send_webhook(settings['webhookUrl'], settings['webhookMethod'], settings.get('webhookSecret', ''))
            channels_tested += 1

    if channel_filter is None or channel_filter == 'ntfy':
        if settings['ntfyEnabled'] and settings['ntfyTopic']:
            result['results']['ntfy'] = send_ntfy(settings['ntfyServer'], settings['ntfyTopic'], settings['ntfyPriority'])
            channels_tested += 1

    if channels_tested == 0:
        result['status'] = 'error'
        if channel_filter:
            result['message'] = f'{channel_filter} channel is not enabled'
        else:
            result['message'] = 'No notification channels configured'
    else:
        successes = sum(1 for r in result['results'].values() if r.get('success'))
        if successes == 0:
            result['status'] = 'error'
            result['message'] = 'All notification tests failed'

    print(json.dumps(result))


if __name__ == '__main__':
    main()
