#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Update DNS records with multi-gateway failover support (v2)
"""

import argparse
import json
import sys
import os
import time
import re
import subprocess
import xml.etree.ElementTree as ET
import syslog

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI
from gateway_health import (get_gateway_ip, get_opnsense_gateway_status, is_gateway_up,
                           write_state_file, verify_dns_propagation)

STATE_FILE = '/var/run/hclouddns_state.json'
SIMULATION_FILE = '/var/run/hclouddns_simulation.json'
CONFIG_FILE = '/conf/config.xml'
HISTORY_DIR = '/var/log/hclouddns'
HISTORY_FILE = '/var/log/hclouddns/history.jsonl'


def load_simulation():
    """Load simulation settings"""
    if os.path.exists(SIMULATION_FILE):
        try:
            with open(SIMULATION_FILE, 'r') as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            pass
    return {'active': False, 'simulatedDown': []}


def log(message, priority=syslog.LOG_INFO):
    """Log to syslog"""
    syslog.openlog('hclouddns', syslog.LOG_PID, syslog.LOG_LOCAL4)
    syslog.syslog(priority, message)


def add_history_entry(entry, account, old_ip, new_ip, action='update'):
    """
    Add a history entry to JSONL file for DNS change tracking.
    Each line is a self-contained JSON object.
    """
    import uuid as uuid_mod
    import fcntl

    try:
        os.makedirs(HISTORY_DIR, mode=0o700, exist_ok=True)

        record = {
            'uuid': str(uuid_mod.uuid4()),
            'timestamp': int(time.time()),
            'action': action,
            'accountUuid': account.get('uuid', ''),
            'accountName': account.get('name', ''),
            'zoneId': entry.get('zoneId', ''),
            'zoneName': entry.get('zoneName', ''),
            'recordName': entry.get('recordName', ''),
            'recordType': entry.get('recordType', ''),
            'oldValue': old_ip or '',
            'oldTtl': entry.get('ttl', 300),
            'newValue': new_ip or '',
            'newTtl': entry.get('ttl', 300),
            'reverted': False
        }

        line = json.dumps(record) + '\n'

        fd = os.open(HISTORY_FILE, os.O_WRONLY | os.O_CREAT | os.O_APPEND, 0o600)
        try:
            with os.fdopen(fd, 'a') as f:
                fcntl.flock(f, fcntl.LOCK_EX)
                try:
                    f.write(line)
                finally:
                    fcntl.flock(f, fcntl.LOCK_UN)
        except Exception:
            try:
                os.close(fd)
            except OSError:
                pass
            raise

        os.chmod(HISTORY_FILE, 0o600)

        log(f"History: {action} {entry['recordName']}.{entry['zoneName']} "
            f"{entry['recordType']} {old_ip} -> {new_ip}")
        return True

    except Exception as e:
        log(f"Failed to add history entry: {str(e)}", syslog.LOG_ERR)
        return False


def cleanup_old_history(retention_days):
    """Remove history entries older than retention_days from JSONL file"""
    import fcntl

    if retention_days <= 0:
        return 0

    if not os.path.exists(HISTORY_FILE):
        return 0

    cutoff_time = int(time.time()) - (retention_days * 86400)
    removed = 0

    try:
        with open(HISTORY_FILE, 'r+') as f:
            fcntl.flock(f, fcntl.LOCK_EX)
            try:
                lines = f.readlines()
                kept = []
                for line in lines:
                    line = line.strip()
                    if not line:
                        continue
                    try:
                        entry = json.loads(line)
                        if entry.get('timestamp', 0) >= cutoff_time:
                            kept.append(line + '\n')
                        else:
                            removed += 1
                    except json.JSONDecodeError:
                        continue

                if removed > 0:
                    f.seek(0)
                    f.truncate()
                    f.writelines(kept)
                    log(f"History cleanup: removed {removed} entries older than {retention_days} days")

            finally:
                fcntl.flock(f, fcntl.LOCK_UN)

    except Exception as e:
        log(f"Failed to cleanup history: {str(e)}", syslog.LOG_ERR)

    return removed


def parse_ttl(ttl_raw):
    """Parse TTL value from config - handles '_60' format and plain '60'"""
    if not ttl_raw:
        return 300
    # Handle OptionField format: "_60" -> 60 or "opt60" -> 60
    if ttl_raw.startswith('_'):
        ttl_raw = ttl_raw[1:]
    elif ttl_raw.startswith('opt'):
        ttl_raw = ttl_raw[3:]
    try:
        return int(ttl_raw)
    except ValueError:
        return 300


def send_ntfy(settings, title, message, tags=''):
    """Send notification via ntfy"""
    import urllib.request
    import urllib.error

    if not settings.get('ntfyEnabled') or not settings.get('ntfyTopic'):
        return False

    try:
        server = settings.get('ntfyServer', 'https://ntfy.sh').rstrip('/')
        topic = settings['ntfyTopic']
        url = f"{server}/{topic}"

        priority_map = {
            'min': '1', 'low': '2', 'default': '3', 'high': '4', 'urgent': '5'
        }
        priority = priority_map.get(settings.get('ntfyPriority', 'default'), '3')

        headers = {
            'Title': title,
            'Priority': priority,
        }
        if tags:
            headers['Tags'] = tags

        req = urllib.request.Request(
            url,
            data=message.encode('utf-8'),
            headers=headers,
            method='POST'
        )

        with urllib.request.urlopen(req, timeout=10):
            log(f"Sent ntfy notification: {title}")
            return True
    except Exception as e:
        log(f"Failed to send ntfy notification: {e}", syslog.LOG_ERR)
        return False


def send_email(settings, subject, body):
    """Send notification via SMTP"""
    import smtplib
    from email.mime.text import MIMEText

    if not settings.get('emailEnabled') or not settings.get('emailTo'):
        return False

    server_host = settings.get('smtpServer', '')
    if not server_host:
        log("Email: SMTP server not configured", syslog.LOG_WARNING)
        return False

    try:
        to_addr = settings['emailTo']
        from_addr = settings.get('emailFrom', '') or f"hclouddns@{server_host}"
        port = int(settings.get('smtpPort', 587))
        tls_mode = settings.get('smtpTls', 'starttls')
        user = settings.get('smtpUser', '')
        password = settings.get('smtpPassword', '')

        msg = MIMEText(body)
        msg['Subject'] = subject
        msg['From'] = from_addr
        msg['To'] = to_addr

        if tls_mode == 'ssl':
            smtp = smtplib.SMTP_SSL(server_host, port, timeout=15)
        else:
            smtp = smtplib.SMTP(server_host, port, timeout=15)

        try:
            if tls_mode == 'starttls':
                smtp.starttls()
            if user and password:
                smtp.login(user, password)
            smtp.sendmail(from_addr, [to_addr], msg.as_string())
            log(f"Sent email notification: {subject}")
            return True
        finally:
            smtp.quit()
    except Exception as e:
        log(f"Email failed: {e}", syslog.LOG_ERR)
        return False


def send_webhook(settings, event_type, data):
    """Send notification via webhook"""
    import urllib.request
    import urllib.error

    if not settings.get('webhookEnabled') or not settings.get('webhookUrl'):
        return False

    try:
        url = settings['webhookUrl']
        method = settings.get('webhookMethod', 'POST')

        payload = {
            'event': event_type,
            'timestamp': int(time.time()),
            'plugin': 'os-hclouddns',
            **data
        }

        json_data = json.dumps(payload).encode('utf-8')
        headers = {'Content-Type': 'application/json'}

        if settings.get('webhookSecret'):
            import hmac
            import hashlib
            timestamp = str(int(time.time()))
            sig = hmac.new(
                settings['webhookSecret'].encode(),
                timestamp.encode() + b'.' + json_data,
                hashlib.sha256
            ).hexdigest()
            headers['X-HCloudDNS-Signature'] = sig
            headers['X-HCloudDNS-Timestamp'] = timestamp

        req = urllib.request.Request(url, data=json_data, headers=headers, method=method)

        with urllib.request.urlopen(req, timeout=10):
            log(f"Sent webhook notification: {event_type}")
            return True
    except Exception as e:
        log(f"Failed to send webhook notification: {e}", syslog.LOG_ERR)
        return False


def send_notification(config, event_type, entry, old_ip=None, new_ip=None, error_msg=None):
    """Send notifications for DNS events based on configuration (single event)"""
    notifications = config.get('notifications', {})

    if not notifications.get('enabled'):
        return

    # Check if this event type should trigger a notification
    should_notify = False
    if event_type == 'update' and notifications.get('notifyOnUpdate'):
        should_notify = True
    elif event_type == 'failover' and notifications.get('notifyOnFailover'):
        should_notify = True
    elif event_type == 'failback' and notifications.get('notifyOnFailback'):
        should_notify = True
    elif event_type == 'error' and notifications.get('notifyOnError'):
        should_notify = True

    if not should_notify:
        return

    # Build notification message
    record_name = f"{entry['recordName']}.{entry['zoneName']}"

    if event_type == 'update':
        title = f"DNS Updated: {record_name}"
        message = f"Record {record_name} ({entry['recordType']}) updated"
        if old_ip and new_ip:
            message += f"\nOld IP: {old_ip}\nNew IP: {new_ip}"
        tags = 'arrows_counterclockwise,hclouddns'
    elif event_type == 'failover':
        title = f"DNS Failover: {record_name}"
        message = f"Record {record_name} switched to failover gateway"
        if new_ip:
            message += f"\nNew IP: {new_ip}"
        tags = 'warning,hclouddns'
    elif event_type == 'failback':
        title = f"DNS Failback: {record_name}"
        message = f"Record {record_name} returned to primary gateway"
        if new_ip:
            message += f"\nNew IP: {new_ip}"
        tags = 'white_check_mark,hclouddns'
    elif event_type == 'error':
        title = f"DNS Error: {record_name}"
        message = f"Error updating {record_name}: {error_msg or 'Unknown error'}"
        tags = 'x,hclouddns'
    else:
        return

    # Send to all enabled channels
    send_ntfy(notifications, title, message, tags)
    send_webhook(notifications, event_type, {
        'record': record_name,
        'type': entry['recordType'],
        'old_ip': old_ip,
        'new_ip': new_ip,
        'error': error_msg
    })


def _get_base_domain(record):
    """Extract base domain from FQDN (e.g., 'www.example.com' -> 'example.com')"""
    parts = record.split('.')
    if len(parts) >= 2:
        return '.'.join(parts[-2:])
    return record


def _group_by_domain(items, key='record'):
    """Group items by their base domain"""
    from collections import OrderedDict
    grouped = OrderedDict()
    for item in items:
        domain = _get_base_domain(item[key])
        if domain not in grouped:
            grouped[domain] = []
        grouped[domain].append(item)
    return grouped


def send_batch_notification(config, batch_results):
    """
    Send a single batch notification summarizing all DNS changes.

    Title format:
    - Failover:  "HCloudDNS: Failover WAN_Primary -> WAN_Backup"
    - Failback:  "HCloudDNS: Failback WAN_Backup -> WAN_Primary"
    - DynIP:     "HCloudDNS: DynIP Update on WAN_Primary"
    - Error:     "HCloudDNS: Error"

    Body: List of affected records (no duplication)
    """
    notifications = config.get('notifications', {})

    if not notifications.get('enabled'):
        return

    updates = batch_results.get('updates', [])
    failovers = batch_results.get('failovers', [])
    failbacks = batch_results.get('failbacks', [])
    errors = batch_results.get('errors', [])

    # Determine notification type - only ONE type per notification (priority order)
    # Failover/Failback already contains the updates, so don't show both
    title = None
    tags = 'hclouddns'
    records_to_show = []

    if failovers and notifications.get('notifyOnFailover'):
        # Failover notification
        first_fo = failovers[0]
        from_gw = first_fo.get('from_gateway', '?')
        to_gw = first_fo.get('to_gateway', '?')
        is_maintenance = first_fo.get('maintenance', False)
        if is_maintenance:
            title = f"HCloudDNS: Maintenance Failover {from_gw} -> {to_gw}"
        else:
            title = f"HCloudDNS: Failover {from_gw} -> {to_gw}"
        tags = 'warning,hclouddns'
        records_to_show = failovers

    elif failbacks and notifications.get('notifyOnFailback'):
        # Failback notification
        first_fb = failbacks[0]
        from_gw = first_fb.get('from_gateway', '?')
        to_gw = first_fb.get('to_gateway', '?')
        title = f"HCloudDNS: Failback {from_gw} -> {to_gw}"
        tags = 'white_check_mark,hclouddns'
        records_to_show = failbacks

    elif updates and notifications.get('notifyOnUpdate'):
        # Regular DynIP update - get gateway name from first update
        gateway_name = updates[0].get('gateway', 'Gateway')
        title = f"HCloudDNS: DynIP Update on {gateway_name}"
        tags = 'arrows_counterclockwise,hclouddns'
        records_to_show = updates

    elif errors and notifications.get('notifyOnError'):
        # Error notification
        title = f"HCloudDNS: {len(errors)} Error(s)"
        tags = 'x,hclouddns'

    if not title:
        return  # Nothing to notify

    # Build message body
    lines = []

    if records_to_show:
        grouped = _group_by_domain(records_to_show[:15])
        first_domain = True

        for domain, domain_records in grouped.items():
            if not first_domain:
                lines.append("")  # Empty line between domains
            first_domain = False

            for r in domain_records:
                lines.append(f"{r['record']}")
                lines.append(f"  → {r['new_ip']}")

        if len(records_to_show) > 15:
            lines.append("")
            lines.append(f"... +{len(records_to_show) - 15} more")

    if errors and notifications.get('notifyOnError'):
        if lines:
            lines.append("")
            lines.append("---")
            lines.append("")

        grouped = _group_by_domain(errors[:10])
        first_domain = True

        for domain, domain_errors in grouped.items():
            if not first_domain:
                lines.append("")
            first_domain = False

            for e in domain_errors:
                lines.append(f"{e['record']}")
                lines.append(f"  ✗ {e['error']}")

    # Add propagation status if available
    propagation = batch_results.get('propagation')
    if propagation and propagation.get('total', 0) > 0:
        lines.append("")
        verified = propagation['verified']
        total = propagation['total']
        if verified == total:
            lines.append(f"DNS propagated: {verified}/{total}")
        else:
            lines.append(f"DNS propagation pending: {verified}/{total}")

    message = "\n".join(lines)

    # Send batch notification
    send_ntfy(notifications, title, message, tags)
    send_email(notifications, title, message)
    send_webhook(notifications, 'batch_update', {
        'updates': len(updates),
        'failovers': len(failovers),
        'failbacks': len(failbacks),
        'errors': len(errors),
        'propagation': propagation,
        'details': batch_results
    })


def get_carp_status(vhid_filter=''):
    """
    Determine CARP status by parsing ifconfig output.

    Args:
        vhid_filter: If set, only check this specific VHID number.
                     Empty string means check all CARP interfaces.

    Returns dict with:
        is_master: True if this node should run DNS updates
        status: 'master', 'backup', or 'none' (no CARP interfaces)
        interfaces: dict of CARP interface states
    """
    result = {
        'is_master': True,  # fail-open: default to master
        'status': 'none',
        'interfaces': {}
    }

    try:
        output = subprocess.check_output(
            ['/sbin/ifconfig', '-a'],
            timeout=5,
            stderr=subprocess.DEVNULL
        ).decode('utf-8', errors='replace')

        # Parse CARP interfaces and their status
        # Format: "carp: MASTER vhid N advbase N advskew N"
        # or:     "carp: BACKUP vhid N advbase N advskew N"
        current_if = None
        for line in output.splitlines():
            # Track current interface name
            if_match = re.match(r'^(\S+):\s+flags=', line)
            if if_match:
                current_if = if_match.group(1)
                continue

            carp_match = re.search(r'carp:\s+(MASTER|BACKUP|INIT)\s+vhid\s+(\d+)', line)
            if carp_match and current_if:
                state = carp_match.group(1)
                vhid = carp_match.group(2)
                key = f"{current_if}_vhid{vhid}"
                result['interfaces'][key] = state

        # Filter by specific VHID if configured
        if vhid_filter:
            filtered = {k: v for k, v in result['interfaces'].items()
                        if k.endswith(f'_vhid{vhid_filter}')}
            if not filtered and result['interfaces']:
                log(f'CARP check: VHID {vhid_filter} not found, '
                    f'available: {list(result["interfaces"].keys())} - assuming MASTER (fail-open)',
                    syslog.LOG_WARNING)
                result['status'] = 'none'
                result['is_master'] = True
                return result
            result['interfaces'] = filtered

        if not result['interfaces']:
            # No CARP interfaces found -> standalone system, fail-open
            result['status'] = 'none'
            result['is_master'] = True
            return result

        # Determine status from (filtered) interfaces
        # If ANY checked interface is BACKUP -> this node is BACKUP
        states = set(result['interfaces'].values())
        if 'BACKUP' in states:
            result['status'] = 'backup'
            result['is_master'] = False
        elif 'MASTER' in states:
            result['status'] = 'master'
            result['is_master'] = True
        else:
            # All INIT or unknown -> fail-open
            result['status'] = 'init'
            result['is_master'] = True

    except subprocess.TimeoutExpired:
        log('CARP check: ifconfig timed out, assuming MASTER (fail-open)', syslog.LOG_WARNING)
    except Exception as e:
        log(f'CARP check: error ({e}), assuming MASTER (fail-open)', syslog.LOG_WARNING)

    return result


def load_config():
    """Load configuration from OPNsense config.xml"""
    config = {
        'enabled': False,
        'checkInterval': 300,
        'failoverEnabled': False,
        'failbackEnabled': True,
        'failbackDelay': 60,
        'verbose': False,
        'carpAware': False,
        'historyRetentionDays': 7,
        'accounts': {},
        'gateways': {},
        'entries': [],
        'notifications': {}
    }

    try:
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()

        hcloud = root.find('.//OPNsense/HCloudDNS')
        if hcloud is None:
            return config

        # General settings
        general = hcloud.find('general')
        if general is not None:
            config['enabled'] = general.findtext('enabled', '0') == '1'
            config['checkInterval'] = int(general.findtext('checkInterval', '300'))
            config['verbose'] = general.findtext('verbose', '0') == '1'
            config['failoverEnabled'] = general.findtext('failoverEnabled', '0') == '1'
            config['failbackEnabled'] = general.findtext('failbackEnabled', '1') == '1'
            config['failbackDelay'] = int(general.findtext('failbackDelay', '60'))
            config['historyRetentionDays'] = int(general.findtext('historyRetentionDays', '7'))
            config['forceInterval'] = int(general.findtext('forceInterval', '0'))
            config['carpAware'] = general.findtext('carpAware', '0') == '1'
            config['carpVhid'] = general.findtext('carpVhid', '')
            config['propagationCheck'] = general.findtext('propagationCheck', '1') == '1'
            config['propagationRetries'] = int(general.findtext('propagationRetries', '3'))
            config['propagationDelay'] = int(general.findtext('propagationDelay', '2'))

        # Accounts (API tokens)
        accounts = hcloud.find('accounts')
        if accounts is not None:
            for acc in accounts.findall('account'):
                uuid = acc.get('uuid', '')
                if not uuid:
                    continue
                config['accounts'][uuid] = {
                    'uuid': uuid,
                    'enabled': acc.findtext('enabled', '1') == '1',
                    'name': acc.findtext('name', ''),
                    'apiType': acc.findtext('apiType', 'cloud'),
                    'apiToken': acc.findtext('apiToken', '')
                }

        # Gateways
        gateways = hcloud.find('gateways')
        if gateways is not None:
            for gw in gateways.findall('gateway'):
                uuid = gw.get('uuid', '')
                if not uuid:
                    continue
                config['gateways'][uuid] = {
                    'uuid': uuid,
                    'enabled': gw.findtext('enabled', '1') == '1',
                    'name': gw.findtext('name', ''),
                    'interface': gw.findtext('interface', ''),
                    'priority': int(gw.findtext('priority', '10')),
                    'checkipMethod': gw.findtext('checkipMethod', 'web_ipify'),
                    'healthCheckTarget': gw.findtext('healthCheckTarget', '8.8.8.8'),
                    'maintenance': gw.findtext('maintenance', '0') == '1',
                    'maintenanceScheduled': gw.findtext('maintenanceScheduled', '0') == '1',
                    'maintenanceStart': gw.findtext('maintenanceStart', ''),
                    'maintenanceEnd': gw.findtext('maintenanceEnd', '')
                }

        # Entries
        entries = hcloud.find('entries')
        if entries is not None:
            for entry in entries.findall('entry'):
                uuid = entry.get('uuid', '')
                if not uuid:
                    continue
                config['entries'].append({
                    'uuid': uuid,
                    'enabled': entry.findtext('enabled', '1') == '1',
                    'account': entry.findtext('account', ''),
                    'zoneId': entry.findtext('zoneId', ''),
                    'zoneName': entry.findtext('zoneName', ''),
                    'recordId': entry.findtext('recordId', ''),
                    'recordName': entry.findtext('recordName', ''),
                    'recordType': entry.findtext('recordType', 'A'),
                    'primaryGateway': entry.findtext('primaryGateway', ''),
                    'failoverGateway': entry.findtext('failoverGateway', ''),
                    'ttl': parse_ttl(entry.findtext('ttl', '300')),
                    'currentIp': entry.findtext('currentIp', ''),
                    'status': entry.findtext('status', 'pending')
                })

        # Notification settings
        notifications = hcloud.find('notifications')
        if notifications is not None:
            config['notifications'] = {
                'enabled': notifications.findtext('enabled', '0') == '1',
                'notifyOnUpdate': notifications.findtext('notifyOnUpdate', '1') == '1',
                'notifyOnFailover': notifications.findtext('notifyOnFailover', '1') == '1',
                'notifyOnFailback': notifications.findtext('notifyOnFailback', '1') == '1',
                'notifyOnError': notifications.findtext('notifyOnError', '1') == '1',
                'notifyOnMaintenance': notifications.findtext('notifyOnMaintenance', '0') == '1',
                'emailEnabled': notifications.findtext('emailEnabled', '0') == '1',
                'emailTo': notifications.findtext('emailTo', ''),
                'emailFrom': notifications.findtext('emailFrom', ''),
                'smtpServer': notifications.findtext('smtpServer', ''),
                'smtpPort': notifications.findtext('smtpPort', '587'),
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

    except Exception as e:
        log(f'Error loading config: {str(e)}', syslog.LOG_ERR)

    return config


def load_runtime_state():
    """Load runtime state from JSON file"""
    if os.path.exists(STATE_FILE):
        try:
            with open(STATE_FILE, 'r') as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            pass
    return {
        'gateways': {},
        'entries': {},
        'failoverHistory': [],
        'lastUpdate': 0
    }


def save_runtime_state(state):
    """Save runtime state to JSON file"""
    try:
        write_state_file(STATE_FILE, state)
    except IOError as e:
        log(f'Error saving state: {str(e)}', syslog.LOG_ERR)


def _is_in_maintenance_window(gw):
    """Check if current time is within a scheduled maintenance window."""
    if not gw.get('maintenanceScheduled'):
        return False
    start_str = gw.get('maintenanceStart', '')
    end_str = gw.get('maintenanceEnd', '')
    if not start_str or not end_str:
        return False
    try:
        from datetime import datetime
        now = datetime.now()
        start = datetime.fromisoformat(start_str)
        end = datetime.fromisoformat(end_str)
        return start <= now <= end
    except (ValueError, TypeError):
        return False


def _is_past_maintenance_window(gw):
    """Check if current time is past a scheduled maintenance window end."""
    if not gw.get('maintenanceScheduled'):
        return False
    end_str = gw.get('maintenanceEnd', '')
    if not end_str:
        return False
    try:
        from datetime import datetime
        now = datetime.now()
        end = datetime.fromisoformat(end_str)
        return now > end
    except (ValueError, TypeError):
        return False


def _clear_maintenance_in_config(uuid):
    """Clear maintenance fields for a gateway in config.xml."""
    try:
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()
        gateways = root.find('.//OPNsense/HCloudDNS/gateways')
        if gateways is not None:
            for gw in gateways.findall('gateway'):
                if gw.get('uuid') == uuid:
                    for field in ['maintenance', 'maintenanceScheduled', 'maintenanceStart', 'maintenanceEnd']:
                        node = gw.find(field)
                        if node is not None:
                            if field in ('maintenance', 'maintenanceScheduled'):
                                node.text = '0'
                            else:
                                node.text = ''
                    tree.write('/conf/config.xml', xml_declaration=True, encoding='UTF-8')
                    log(f"Cleared maintenance fields for gateway {uuid}")
                    return True
    except Exception as e:
        log(f"Failed to clear maintenance in config: {e}", syslog.LOG_ERR)
    return False


def check_all_gateways(config, state):
    """Check health and get IPs for all gateways"""
    simulation = load_simulation()
    opnsense_status = get_opnsense_gateway_status()

    for uuid, gw in config['gateways'].items():
        if not gw['enabled']:
            continue

        if uuid not in state['gateways']:
            state['gateways'][uuid] = {
                'status': 'unknown',
                'ipv4': None,
                'ipv6': None,
                'lastCheck': 0,
                'failCount': 0,
                'upSince': None,
                'simulated': False,
                'maintenance': False
            }

        gw_state = state['gateways'][uuid]

        # Get current IP
        ip_result = get_gateway_ip(uuid, gw)
        gw_state['ipv4'] = ip_result.get('ipv4')
        gw_state['ipv6'] = ip_result.get('ipv6')

        # Check maintenance mode
        in_maintenance = gw.get('maintenance', False)

        # Check scheduled maintenance window
        if not in_maintenance and _is_in_maintenance_window(gw):
            in_maintenance = True

        # Auto-clear expired scheduled maintenance
        if gw.get('maintenanceScheduled') and _is_past_maintenance_window(gw):
            log(f"Maintenance window expired for gateway '{gw['name']}', auto-clearing")
            _clear_maintenance_in_config(uuid)
            in_maintenance = False
            gw['maintenance'] = False
            gw['maintenanceScheduled'] = False

        gw_state['maintenance'] = in_maintenance

        if in_maintenance:
            old_status = gw_state.get('status', 'unknown')
            gw_state['status'] = 'maintenance'
            # Don't increment failCount for maintenance (not a real failure)
            if old_status not in ('maintenance', 'unknown'):
                log(f"MAINTENANCE: Gateway '{gw['name']}' entering maintenance mode", syslog.LOG_WARNING)
            continue

        # Check if this gateway is simulated as down
        is_simulated_down = simulation.get('active', False) and uuid in simulation.get('simulatedDown', [])
        gw_state['simulated'] = is_simulated_down

        if is_simulated_down:
            # Override status to down for simulation
            old_status = gw_state.get('status', 'unknown')
            gw_state['status'] = 'down'
            gw_state['failCount'] = gw_state.get('failCount', 0) + 1
            if old_status == 'up':
                log(f"SIMULATION: Gateway '{gw['name']}' is DOWN (simulated)", syslog.LOG_WARNING)
            continue

        # Use OPNsense's dpinger status (matched by interface) as primary health source
        interface = gw.get('interface', '')
        dpinger_healthy = is_gateway_up(interface, opnsense_status)
        has_ip = gw_state['ipv4'] or gw_state['ipv6']
        new_status = 'up' if (dpinger_healthy and has_ip) else 'down'

        old_status = gw_state.get('status', 'unknown')
        gw_state['lastCheck'] = int(time.time())

        if new_status == 'up':
            if old_status != 'up':
                gw_state['upSince'] = int(time.time())
                log(f"Gateway '{gw['name']}' is UP (IP: {gw_state['ipv4']})")
            gw_state['failCount'] = 0
        else:
            gw_state['failCount'] = gw_state.get('failCount', 0) + 1
            if old_status == 'up':
                reason = 'no IP' if not has_ip else 'dpinger: down'
                log(f"Gateway '{gw['name']}' is DOWN ({reason}, failCount: {gw_state['failCount']})", syslog.LOG_WARNING)

        gw_state['status'] = new_status

    return state


def determine_active_gateway(entry, config, state):
    """
    Determine which gateway should be active for an entry

    Returns: (gateway_uuid, gateway_config, reason)
    """
    primary_uuid = entry['primaryGateway']
    failover_uuid = entry.get('failoverGateway', '')

    primary_gw = config['gateways'].get(primary_uuid)
    failover_gw = config['gateways'].get(failover_uuid) if failover_uuid else None

    primary_state = state['gateways'].get(primary_uuid, {})
    failover_state = state['gateways'].get(failover_uuid, {}) if failover_uuid else {}

    # Gateway is "up" if health check passes OR if we have a valid IP
    primary_has_ip = primary_state.get('ipv4') or primary_state.get('ipv6')
    failover_has_ip = failover_state.get('ipv4') or failover_state.get('ipv6')

    primary_healthy = primary_state.get('status') == 'up'
    failover_healthy = failover_state.get('status') == 'up'

    # Maintenance mode is treated as down for failover purposes
    primary_in_maintenance = primary_state.get('status') == 'maintenance'
    if primary_in_maintenance:
        primary_healthy = False

    # Primary is usable if enabled and has IP
    primary_usable = primary_gw and primary_gw['enabled'] and primary_has_ip
    failover_usable = failover_gw and failover_gw['enabled'] and failover_has_ip

    entry_state = state['entries'].get(entry['uuid'], {})
    current_active = entry_state.get('activeGateway')

    # If failover is enabled, use health status for decisions
    if config['failoverEnabled']:
        # Primary is healthy and usable
        if primary_healthy and primary_usable:
            # Check if we need failback
            if current_active == failover_uuid and config['failbackEnabled']:
                up_since = primary_state.get('upSince', 0)
                if up_since and (time.time() - up_since) >= config['failbackDelay']:
                    return primary_uuid, primary_gw, 'failback'
                else:
                    return failover_uuid, failover_gw, 'failback_pending'
            return primary_uuid, primary_gw, 'primary'

        # Primary is down but failover is healthy
        if failover_healthy and failover_usable:
            return failover_uuid, failover_gw, 'failover'

        # Both unhealthy - use whichever has IP, prefer primary
        if primary_usable:
            return primary_uuid, primary_gw, 'primary_degraded'
        if failover_usable:
            return failover_uuid, failover_gw, 'failover_degraded'
    else:
        # Failover disabled - just use primary if it has IP
        if primary_usable:
            return primary_uuid, primary_gw, 'primary'

    # Both down or no failover configured
    if primary_gw:
        return primary_uuid, primary_gw, 'primary_down'

    return None, None, 'no_gateway'


def update_dns_record(api, entry, target_ip, state, config=None, force=False, dry_run=False, skip_propagation=False):
    """Update DNS record at Hetzner"""
    zone_id = entry['zoneId']
    record_name = entry['recordName']
    record_type = entry['recordType']
    ttl = entry['ttl']

    try:
        # Check current value and TTL first
        records = api.list_records(zone_id)
        current_value = None
        current_ttl = None
        for rec in records:
            if rec.get('name') == record_name and rec.get('type') == record_type:
                current_value = rec.get('value')
                current_ttl = rec.get('ttl')
                break

        # Only skip if BOTH value AND TTL match (unless force update)
        if current_value == target_ip and current_ttl == ttl:
            if force:
                log(f"Force-updating {record_name}.{entry['zoneName']} (interval expired)")
            else:
                return True, 'unchanged', None

        # Dry-run: report what would change without making API calls
        if dry_run:
            if current_value is None:
                return True, 'would_create', None
            return True, 'would_update', None

        # Use the rrsets API to update/create record
        success, message = api.update_record(zone_id, record_name, record_type, target_ip, ttl)

        if success:
            log(f"Updated {record_name}.{entry['zoneName']} {record_type} -> {target_ip}")

            # DNS propagation verification (skip during maintenance for faster failover)
            propagation = None
            if config and config.get('propagationCheck', False) and not skip_propagation:
                retries = config.get('propagationRetries', 3)
                delay = config.get('propagationDelay', 2)
                for attempt in range(retries):
                    propagation = verify_dns_propagation(
                        record_name, entry['zoneName'], record_type, target_ip
                    )
                    if propagation['propagated']:
                        log(f"DNS propagated for {record_name}.{entry['zoneName']} "
                            f"(attempt {attempt + 1}/{retries})")
                        break
                    if attempt < retries - 1:
                        time.sleep(delay)
                if propagation and not propagation['propagated']:
                    log(f"DNS propagation pending for {record_name}.{entry['zoneName']} "
                        f"after {retries} attempts", syslog.LOG_WARNING)

            return True, 'updated', propagation
        else:
            log(f"DNS update failed for {record_name}.{entry['zoneName']}: {message}", syslog.LOG_ERR)
            return False, message, None

    except Exception as e:
        log(f"DNS update failed for {record_name}.{entry['zoneName']}: {str(e)}", syslog.LOG_ERR)
        return False, str(e), None


def _process_single_entry(entry, account, api, config, state, state_lock, dry_run=False):
    """
    Process a single DNS entry. Thread-safe worker function.
    Returns a dict with the result of processing this entry.
    """
    entry_uuid = entry['uuid']
    record_fqdn = f"{entry['recordName']}.{entry['zoneName']}"

    result = {
        'processed': True,
        'updated': False,
        'error': None,
        'failover_event': None,
        'update_event': None,
        'failover_history': None
    }

    # Thread-safe state access
    with state_lock:
        if entry_uuid not in state['entries']:
            state['entries'][entry_uuid] = {
                'hetznerIp': None,
                'lastUpdate': 0,
                'status': 'pending',
                'activeGateway': None
            }
        entry_state = state['entries'][entry_uuid]
        old_active_gw = entry_state.get('activeGateway')
        current_hetzner_ip = entry_state.get('hetznerIp')

    # Determine active gateway (reads from state, thread-safe)
    active_uuid, active_gw, reason = determine_active_gateway(entry, config, state)

    if not active_gw:
        with state_lock:
            state['entries'][entry_uuid]['status'] = 'error'
        result['error'] = {
            'record': record_fqdn,
            'type': entry['recordType'],
            'error': 'No gateway available'
        }
        return result

    # Get target IP from gateway
    with state_lock:
        gw_state = state['gateways'].get(active_uuid, {})
    if entry['recordType'] == 'AAAA':
        target_ip = gw_state.get('ipv6')
    else:
        target_ip = gw_state.get('ipv4')

    if not target_ip:
        log(f"No IP available for entry {record_fqdn}", syslog.LOG_WARNING)
        with state_lock:
            state['entries'][entry_uuid]['status'] = 'error'
        result['error'] = {
            'record': record_fqdn,
            'type': entry['recordType'],
            'error': 'No IP available from gateway'
        }
        return result

    # Track failover/failback events
    if old_active_gw and old_active_gw != active_uuid:
        # Get gateway names for notification
        old_gw_config = config['gateways'].get(old_active_gw, {})
        old_gw_name = old_gw_config.get('name', old_active_gw[:8])
        new_gw_name = active_gw.get('name', active_uuid[:8])

        # Determine if failover is due to maintenance
        primary_state_status = state['gateways'].get(entry['primaryGateway'], {}).get('status', '')
        is_maintenance_failover = primary_state_status == 'maintenance'

        if reason == 'failover':
            failover_reason = 'maintenance' if is_maintenance_failover else 'primary_down'
            log(f"FAILOVER: {record_fqdn} switching from {old_gw_name} to {new_gw_name} ({failover_reason})")
            result['failover_event'] = 'failover'
            result['failover_reason'] = failover_reason
            result['failover_history'] = {
                'timestamp': int(time.time()),
                'entry': entry_uuid,
                'from': old_active_gw,
                'to': active_uuid,
                'reason': failover_reason
            }
            result['from_gateway'] = old_gw_name
            result['to_gateway'] = new_gw_name
        elif reason == 'failback':
            log(f"FAILBACK: {record_fqdn} returning from {old_gw_name} to {new_gw_name}")
            result['failover_event'] = 'failback'
            result['failover_reason'] = 'failback'
            result['failover_history'] = {
                'timestamp': int(time.time()),
                'entry': entry_uuid,
                'from': old_active_gw,
                'to': active_uuid,
                'reason': 'failback'
            }
            result['from_gateway'] = old_gw_name
            result['to_gateway'] = new_gw_name

    # Check force interval
    force_update = False
    if config.get('forceInterval', 0) > 0:
        with state_lock:
            last_update_ts = state['entries'].get(entry_uuid, {}).get('lastUpdate', 0)
        if last_update_ts > 0 and (time.time() - last_update_ts) > config['forceInterval'] * 86400:
            force_update = True

    # Skip propagation check during maintenance (faster failover/failback)
    primary_maint_ended = state['gateways'].get(entry.get('primaryGateway', ''), {}).get('maintenanceEnded', 0)
    is_maintenance_update = (
        result.get('failover_reason') == 'maintenance' or
        (reason == 'failback' and primary_maint_ended and (time.time() - primary_maint_ended) < 120)
    )

    # Update DNS (this is the slow network call - runs in parallel)
    success, update_reason, propagation = update_dns_record(
        api, entry, target_ip, state, config=config, force=force_update, dry_run=dry_run,
        skip_propagation=is_maintenance_update
    )

    # In dry-run mode, report what would happen without changing state
    if dry_run:
        if update_reason in ['would_update', 'would_create']:
            result['updated'] = True
            result['update_event'] = {
                'record': record_fqdn,
                'type': entry['recordType'],
                'old_ip': current_hetzner_ip,
                'new_ip': target_ip,
                'gateway': active_gw.get('name', 'Gateway'),
                'dry_run': True,
                'action': update_reason
            }
        return result

    # Update state with results (thread-safe)
    with state_lock:
        entry_state = state['entries'][entry_uuid]
        entry_state['activeGateway'] = active_uuid

        if success:
            entry_state['hetznerIp'] = target_ip
            entry_state['lastUpdate'] = int(time.time())
            entry_state['status'] = 'active' if reason in ['primary', 'failback'] else 'failover'

            # Store propagation status
            if propagation is not None:
                entry_state['propagated'] = propagation['propagated']
                entry_state['propagationResults'] = propagation['results']
            elif update_reason == 'unchanged':
                pass  # keep existing propagation state
            else:
                entry_state['propagated'] = None

            if update_reason in ['updated', 'created']:
                result['updated'] = True
                result['propagation'] = propagation
                # Add history entry for tracking IP changes
                action = 'create' if update_reason == 'created' else 'update'
                add_history_entry(entry, account, current_hetzner_ip, target_ip, action)
                result['update_event'] = {
                    'record': record_fqdn,
                    'type': entry['recordType'],
                    'old_ip': current_hetzner_ip,
                    'new_ip': target_ip,
                    'gateway': active_gw.get('name', 'Gateway')
                }

            # Add failover/failback notification data
            if result['failover_event']:
                result['failover_notification'] = {
                    'record': record_fqdn,
                    'type': entry['recordType'],
                    'old_ip': current_hetzner_ip,
                    'new_ip': target_ip,
                    'from_gateway': result.get('from_gateway'),
                    'to_gateway': result.get('to_gateway')
                }
        else:
            entry_state['status'] = 'error'
            result['error'] = {
                'record': record_fqdn,
                'type': entry['recordType'],
                'error': update_reason
            }

    return result


def process_entries(config, state, dry_run=False):
    """
    Process all entries and update DNS as needed.
    Uses parallel processing with deduplication:
    1. First deduplicate entries (same zone/record/type processed only once)
    2. Process all unique entries in parallel using ThreadPoolExecutor
    3. Collect all changes for batch notification at the end
    """
    import threading
    from concurrent.futures import ThreadPoolExecutor, as_completed

    results = {
        'processed': 0,
        'updated': 0,
        'errors': 0,
        'failovers': 0,
        'failbacks': 0,
        'skipped_no_account': 0,
        'skipped_duplicate': 0
    }

    # Batch notification data - collect all events for single notification
    batch_events = {
        'updates': [],
        'failovers': [],
        'failbacks': [],
        'errors': []
    }

    # Track propagation results
    propagation_results = []

    # Lock for thread-safe state access
    state_lock = threading.Lock()

    # Phase 1: Deduplicate and prepare entries
    # Key: (zone_id, record_name, record_type) to catch config duplicates
    unique_entries = {}
    api_cache = {}

    for entry in config['entries']:
        if not entry['enabled'] or entry['status'] == 'paused':
            continue

        account_uuid = entry.get('account', '')

        # Create unique key for this record to prevent duplicates
        record_key = (entry['zoneId'], entry['recordName'], entry['recordType'])
        if record_key in unique_entries:
            log(f"Skipping duplicate entry {entry['recordName']}.{entry['zoneName']} {entry['recordType']}")
            results['skipped_duplicate'] += 1
            continue

        # Get account for this entry
        account = config['accounts'].get(account_uuid)
        if not account or not account['enabled'] or not account['apiToken']:
            log(f"No valid account for entry {entry['recordName']}.{entry['zoneName']}", syslog.LOG_WARNING)
            results['skipped_no_account'] += 1
            continue

        # Get or create API instance for this account
        if account_uuid not in api_cache:
            api_cache[account_uuid] = HCloudAPI(
                account['apiToken'],
                api_type=account['apiType'],
                verbose=config['verbose']
            )

        # Store entry with its dependencies for parallel processing
        unique_entries[record_key] = {
            'entry': entry,
            'account': account,
            'api': api_cache[account_uuid]
        }

    # Phase 2: Process all unique entries in parallel
    max_workers = min(10, len(unique_entries)) if unique_entries else 1

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        # Submit all tasks
        future_to_entry = {}
        for record_key, data in unique_entries.items():
            future = executor.submit(
                _process_single_entry,
                data['entry'],
                data['account'],
                data['api'],
                config,
                state,
                state_lock,
                dry_run
            )
            future_to_entry[future] = record_key

        # Collect results as they complete
        for future in as_completed(future_to_entry):
            record_key = future_to_entry[future]
            try:
                result = future.result()

                results['processed'] += 1

                if result.get('updated'):
                    results['updated'] += 1
                    if result.get('propagation') is not None:
                        propagation_results.append(result['propagation'])

                if result.get('error'):
                    results['errors'] += 1
                    batch_events['errors'].append(result['error'])

                if result.get('failover_event') == 'failover':
                    results['failovers'] += 1
                    is_maintenance = result.get('failover_reason') == 'maintenance'
                    notify_maintenance = config.get('notifications', {}).get('notifyOnMaintenance')

                    if is_maintenance and not notify_maintenance:
                        pass
                    else:
                        if result.get('update_event'):
                            if is_maintenance:
                                result['update_event']['maintenance'] = True
                            batch_events['updates'].append(result['update_event'])
                        if result.get('failover_notification'):
                            if is_maintenance:
                                result['failover_notification']['maintenance'] = True
                            batch_events['failovers'].append(result['failover_notification'])
                    if result.get('failover_history'):
                        with state_lock:
                            state['failoverHistory'].append(result['failover_history'])

                elif result.get('failover_event') == 'failback':
                    results['failbacks'] += 1
                    # Check if failback is from a recent maintenance stop
                    primary_gw_uuid = ''
                    for e in config.get('entries', []):
                        if e.get('recordName') == record_key[1] and e.get('recordType') == record_key[2]:
                            primary_gw_uuid = e.get('primaryGateway', '')
                            break
                    maint_ended = state['gateways'].get(primary_gw_uuid, {}).get('maintenanceEnded', 0)
                    is_maintenance_failback = maint_ended and (time.time() - maint_ended) < 120
                    notify_maintenance = config.get('notifications', {}).get('notifyOnMaintenance')

                    if is_maintenance_failback and not notify_maintenance:
                        pass
                    else:
                        if result.get('failover_notification'):
                            batch_events['failbacks'].append(result['failover_notification'])
                        if result.get('update_event'):
                            batch_events['updates'].append(result['update_event'])
                    if result.get('failover_history'):
                        with state_lock:
                            state['failoverHistory'].append(result['failover_history'])

                elif result.get('updated'):
                    # Non-failover, non-failback update (regular IP change)
                    if result.get('update_event'):
                        batch_events['updates'].append(result['update_event'])

            except Exception as e:
                log(f"Error processing entry {record_key}: {str(e)}", syslog.LOG_ERR)
                results['errors'] += 1
                batch_events['errors'].append({
                    'record': f"{record_key[1]}.unknown",
                    'type': record_key[2],
                    'error': str(e)
                })

    # Trim failover history to last 100 entries
    with state_lock:
        if len(state['failoverHistory']) > 100:
            state['failoverHistory'] = state['failoverHistory'][-100:]

    # Check if maintenance started and all entries have failed over
    # Send "Maintenance started" notification only after DNS failover is complete
    maintenance_started_gateways = set()
    if not dry_run:
        for gw_uuid, gw_state_data in state.get('gateways', {}).items():
            maint_started_ts = gw_state_data.get('maintenanceStarted', 0)
            if not maint_started_ts:
                continue
            if (time.time() - maint_started_ts) > 120:
                gw_state_data.pop('maintenanceStarted', None)
                continue

            # Check all entries that use this gateway as primary:
            # have they ALL failed over to a different gateway?
            all_failedover = True
            has_entries = False
            for entry in config['entries']:
                if not entry['enabled'] or entry['status'] == 'paused':
                    continue
                if entry.get('primaryGateway') != gw_uuid:
                    continue
                has_entries = True
                entry_state = state['entries'].get(entry['uuid'], {})
                active_gw = entry_state.get('activeGateway')
                if not active_gw or active_gw == gw_uuid:
                    all_failedover = False
                    break

            if has_entries and all_failedover:
                gw_name = config['gateways'].get(gw_uuid, {}).get('name', gw_uuid[:8])
                try:
                    tree = ET.parse('/conf/config.xml')
                    root = tree.getroot()
                    _send_maintenance_notification(root, gw_name, 'start')
                except Exception as e:
                    log(f"Failed to send maintenance started notification: {e}", syslog.LOG_ERR)
                gw_state_data.pop('maintenanceStarted', None)
                maintenance_started_gateways.add(gw_uuid)
                log(f"Maintenance fully started for gateway {gw_name}: all entries failed over")

    # Check if maintenance ended and all entries have failbacked
    # Send "Maintenance ended" notification only after DNS is fully restored
    maintenance_ended_gateways = set()
    if not dry_run:
        for gw_uuid, gw_state_data in state.get('gateways', {}).items():
            maint_ended_ts = gw_state_data.get('maintenanceEnded', 0)
            if not maint_ended_ts:
                continue
            # Check if within 120s window (maintenance recently ended)
            if (time.time() - maint_ended_ts) > 120:
                # Too old, clear the flag
                gw_state_data.pop('maintenanceEnded', None)
                continue

            # Check all entries that use this gateway as primary:
            # are they ALL back on primary (activeGateway == primaryGateway)?
            all_failbacked = True
            has_entries = False
            for entry in config['entries']:
                if not entry['enabled'] or entry['status'] == 'paused':
                    continue
                if entry.get('primaryGateway') != gw_uuid:
                    continue
                has_entries = True
                entry_state = state['entries'].get(entry['uuid'], {})
                active_gw = entry_state.get('activeGateway')
                if active_gw and active_gw != gw_uuid:
                    all_failbacked = False
                    break

            if has_entries and all_failbacked:
                # All entries are back on primary - send "Maintenance ended" notification
                gw_name = config['gateways'].get(gw_uuid, {}).get('name', gw_uuid[:8])
                try:
                    tree = ET.parse('/conf/config.xml')
                    root = tree.getroot()
                    _send_maintenance_notification(root, gw_name, 'stop')
                except Exception as e:
                    log(f"Failed to send maintenance ended notification: {e}", syslog.LOG_ERR)
                # Clear the flag so we don't send again
                gw_state_data.pop('maintenanceEnded', None)
                maintenance_ended_gateways.add(gw_uuid)
                log(f"Maintenance fully ended for gateway {gw_name}: all entries restored")

    # Remove failover/failback/update events from batch that are already covered
    # by the maintenance start/end notification to avoid duplicate notifications
    maint_notified_gateways = maintenance_started_gateways | maintenance_ended_gateways
    if maint_notified_gateways:
        maint_records = set()
        for entry in config.get('entries', []):
            if entry.get('primaryGateway') in maint_notified_gateways:
                fqdn = entry.get('recordName', '') + '.' + entry.get('zoneName', '')
                maint_records.add(fqdn)
        batch_events['failovers'] = [
            fo for fo in batch_events['failovers']
            if fo.get('record') not in maint_records
        ]
        batch_events['failbacks'] = [
            fb for fb in batch_events['failbacks']
            if fb.get('record') not in maint_records
        ]
        batch_events['updates'] = [
            u for u in batch_events['updates']
            if u.get('record') not in maint_records
        ]

    # Count propagation results
    propagated_count = sum(1 for r in propagation_results if r.get('propagated'))
    total_propagation = len(propagation_results)
    if total_propagation > 0:
        batch_events['propagation'] = {
            'verified': propagated_count,
            'total': total_propagation
        }

    # Send single batch notification with all changes (skip in dry-run)
    if not dry_run:
        send_batch_notification(config, batch_events)

    results['batch_events'] = batch_events
    return results


def _update_gateway_state(uuid, maintenance):
    """Update maintenance flag in the runtime state file immediately."""
    try:
        state = {}
        if os.path.exists(STATE_FILE):
            with open(STATE_FILE, 'r') as f:
                state = json.load(f)
        if 'gateways' not in state:
            state['gateways'] = {}
        if uuid not in state['gateways']:
            state['gateways'][uuid] = {}
        state['gateways'][uuid]['maintenance'] = maintenance
        if maintenance:
            state['gateways'][uuid]['status'] = 'maintenance'
            state['gateways'][uuid].pop('maintenanceEnded', None)
            state['gateways'][uuid]['maintenanceStarted'] = int(time.time())
        else:
            state['gateways'][uuid]['status'] = 'up'
            state['gateways'][uuid]['maintenanceEnded'] = int(time.time())
        write_state_file(STATE_FILE, state)
    except Exception as e:
        log(f"Failed to update gateway state: {e}", syslog.LOG_ERR)


def _get_gateway_name(root, uuid):
    """Get gateway name from config.xml by UUID."""
    gateways = root.find('.//OPNsense/HCloudDNS/gateways')
    if gateways is not None:
        for gw in gateways.findall('gateway'):
            if gw.get('uuid') == uuid:
                return gw.findtext('name', uuid[:8])
    return uuid[:8]


def _send_maintenance_notification(root, gw_name, action):
    """Send a maintenance mode notification (start/stop)."""
    try:
        notifications = root.find('.//OPNsense/HCloudDNS/notifications')
        if notifications is None:
            return
        if notifications.findtext('enabled', '0') != '1':
            return

        settings = {
            'ntfyEnabled': notifications.findtext('ntfyEnabled', '0') == '1',
            'ntfyServer': notifications.findtext('ntfyServer', 'https://ntfy.sh'),
            'ntfyTopic': notifications.findtext('ntfyTopic', ''),
            'ntfyPriority': notifications.findtext('ntfyPriority', 'default'),
            'emailEnabled': notifications.findtext('emailEnabled', '0') == '1',
            'emailTo': notifications.findtext('emailTo', ''),
            'emailFrom': notifications.findtext('emailFrom', ''),
            'smtpServer': notifications.findtext('smtpServer', ''),
            'smtpPort': notifications.findtext('smtpPort', '587'),
            'smtpTls': notifications.findtext('smtpTls', 'starttls'),
            'smtpUser': notifications.findtext('smtpUser', ''),
            'smtpPassword': notifications.findtext('smtpPassword', ''),
            'webhookEnabled': notifications.findtext('webhookEnabled', '0') == '1',
            'webhookUrl': notifications.findtext('webhookUrl', ''),
            'webhookMethod': notifications.findtext('webhookMethod', 'POST'),
            'webhookSecret': notifications.findtext('webhookSecret', ''),
        }

        if action == 'start':
            title = f"Maintenance: {gw_name}"
            message = (f"Gateway '{gw_name}' is entering maintenance mode.\n"
                       f"DNS entries will failover to backup gateways.\n"
                       f"This is a planned maintenance - no action required.")
            tags = 'wrench,maintenance'
        else:
            title = f"Maintenance ended: {gw_name}"
            message = (f"Gateway '{gw_name}' has exited maintenance mode.\n"
                       f"DNS entries will failback to primary gateway.")
            tags = 'white_check_mark,maintenance'

        send_ntfy(settings, title, message, tags)
        send_email(settings, title, message)
        send_webhook(settings, f'maintenance_{action}', {
            'gateway': gw_name,
            'action': action
        })
        log(f"Sent maintenance {action} notification for {gw_name}")
    except Exception as e:
        log(f"Failed to send maintenance notification: {e}", syslog.LOG_ERR)


def handle_maintenance_start(uuid):
    """Set maintenance=1 for a gateway in config.xml and update runtime state."""
    try:
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()
        gateways = root.find('.//OPNsense/HCloudDNS/gateways')
        if gateways is not None:
            for gw in gateways.findall('gateway'):
                if gw.get('uuid') == uuid:
                    maint = gw.find('maintenance')
                    if maint is None:
                        maint = ET.SubElement(gw, 'maintenance')
                    maint.text = '1'
                    tree.write('/conf/config.xml', xml_declaration=True, encoding='UTF-8')
                    _update_gateway_state(uuid, True)
                    gw_name = gw.findtext('name', uuid[:8])
                    # Don't send notification here - it will be sent after
                    # the update cycle confirms all entries have failed over
                    log(f"Maintenance started for gateway {gw_name} ({uuid})")
                    return {'status': 'ok', 'message': 'Maintenance mode started'}
        return {'status': 'error', 'message': 'Gateway not found'}
    except Exception as e:
        return {'status': 'error', 'message': str(e)}


def handle_maintenance_stop(uuid):
    """Clear maintenance for a gateway, update runtime state and send notification."""
    try:
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()
        gw_name = _get_gateway_name(root, uuid)
    except Exception:
        gw_name = uuid[:8]
        root = None

    result = _clear_maintenance_in_config(uuid)
    if result:
        _update_gateway_state(uuid, False)
        # Don't send "Maintenance ended" notification here - it will be sent
        # after the update cycle confirms all entries have failbacked
        log(f"Maintenance stopped for gateway {gw_name} ({uuid})")
        return {'status': 'ok', 'message': 'Maintenance mode stopped'}
    return {'status': 'error', 'message': 'Failed to clear maintenance'}


def handle_maintenance_schedule(uuid, start, end):
    """Set scheduled maintenance window for a gateway."""
    try:
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()
        gateways = root.find('.//OPNsense/HCloudDNS/gateways')
        if gateways is not None:
            for gw in gateways.findall('gateway'):
                if gw.get('uuid') == uuid:
                    for field, value in [('maintenanceScheduled', '1'),
                                         ('maintenanceStart', start),
                                         ('maintenanceEnd', end)]:
                        node = gw.find(field)
                        if node is None:
                            node = ET.SubElement(gw, field)
                        node.text = value
                    tree.write('/conf/config.xml', xml_declaration=True, encoding='UTF-8')
                    log(f"Maintenance scheduled for gateway {uuid}: {start} to {end}")
                    return {'status': 'ok', 'message': f'Maintenance scheduled: {start} to {end}'}
        return {'status': 'error', 'message': 'Gateway not found'}
    except Exception as e:
        return {'status': 'error', 'message': str(e)}


def main():
    parser = argparse.ArgumentParser(description='HCloudDNS DNS updater')
    parser.add_argument('--dry-run', action='store_true', help='Preview changes without making API calls')
    parser.add_argument('--maintenance-start', action='store_true', help='Start maintenance mode for gateway')
    parser.add_argument('--maintenance-stop', action='store_true', help='Stop maintenance mode for gateway')
    parser.add_argument('--maintenance-schedule', action='store_true', help='Schedule maintenance window')
    args, remaining = parser.parse_known_args()

    # Handle maintenance commands
    if args.maintenance_start:
        if not remaining:
            print(json.dumps({'status': 'error', 'message': 'Gateway UUID required'}))
            return
        result = handle_maintenance_start(remaining[0])
        print(json.dumps(result))
        return

    if args.maintenance_stop:
        if not remaining:
            print(json.dumps({'status': 'error', 'message': 'Gateway UUID required'}))
            return
        result = handle_maintenance_stop(remaining[0])
        print(json.dumps(result))
        return

    if args.maintenance_schedule:
        if len(remaining) < 3:
            print(json.dumps({'status': 'error', 'message': 'Usage: --maintenance-schedule UUID START END'}))
            return
        result = handle_maintenance_schedule(remaining[0], remaining[1], remaining[2])
        print(json.dumps(result))
        return

    dry_run = args.dry_run

    result = {
        'status': 'ok',
        'message': '',
        'details': {}
    }
    if dry_run:
        result['dry_run'] = True

    config = load_config()

    if not config['enabled']:
        result['message'] = 'Service is disabled'
        print(json.dumps(result))
        return

    # CARP check: skip DNS updates on BACKUP node
    if config['carpAware']:
        carp = get_carp_status(config.get('carpVhid', ''))
        if not carp['is_master']:
            log(f"CARP BACKUP - DNS update skipped (interfaces: {carp['interfaces']})")
            result['message'] = 'CARP BACKUP - DNS update skipped'
            result['details'] = {'carp_status': carp['status'], 'carp_interfaces': carp['interfaces']}
            print(json.dumps(result))
            return

    if not config['accounts']:
        result['status'] = 'error'
        result['message'] = 'No accounts/tokens configured'
        print(json.dumps(result))
        return

    if not config['gateways']:
        result['status'] = 'error'
        result['message'] = 'No gateways configured'
        print(json.dumps(result))
        return

    if not config['entries']:
        result['message'] = 'No entries configured'
        print(json.dumps(result))
        return

    state = load_runtime_state()

    # Check all gateways
    state = check_all_gateways(config, state)

    # Process entries (API instances created per-account inside)
    update_results = process_entries(config, state, dry_run=dry_run)

    if not dry_run:
        state['lastUpdate'] = int(time.time())
        save_runtime_state(state)

        # Cleanup old history entries
        if config['historyRetentionDays'] > 0:
            cleanup_old_history(config['historyRetentionDays'])

    result['details'] = update_results
    result['message'] = f"Processed {update_results['processed']} entries, {update_results['updated']} updated"

    if update_results.get('skipped_no_account', 0) > 0:
        result['message'] += f", {update_results['skipped_no_account']} skipped (no account)"
    if update_results.get('skipped_duplicate', 0) > 0:
        result['message'] += f", {update_results['skipped_duplicate']} skipped (duplicate)"
    if update_results['failovers'] > 0:
        result['message'] += f", {update_results['failovers']} failovers"
    if update_results['failbacks'] > 0:
        result['message'] += f", {update_results['failbacks']} failbacks"
    if update_results['errors'] > 0:
        result['status'] = 'warning'
        result['message'] += f", {update_results['errors']} errors"

    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
