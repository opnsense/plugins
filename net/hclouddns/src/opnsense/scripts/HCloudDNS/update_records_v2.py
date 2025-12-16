#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Update DNS records with multi-gateway failover support (v2)
"""

import json
import sys
import os
import time
import xml.etree.ElementTree as ET
import syslog

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI
from gateway_health import get_gateway_ip

STATE_FILE = '/var/run/hclouddns_state.json'
SIMULATION_FILE = '/var/run/hclouddns_simulation.json'
CONFIG_FILE = '/conf/config.xml'


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
    Add a history entry to config.xml for DNS change tracking.
    This allows users to see all IP changes over time.
    """
    import uuid as uuid_mod
    import fcntl

    try:
        # Use file locking for safe concurrent access
        with open(CONFIG_FILE, 'r+') as f:
            fcntl.flock(f, fcntl.LOCK_EX)
            try:
                content = f.read()
                tree = ET.ElementTree(ET.fromstring(content))
                root = tree.getroot()

                hcloud = root.find('.//OPNsense/HCloudDNS')
                if hcloud is None:
                    return False

                # Find or create history section
                history = hcloud.find('history')
                if history is None:
                    history = ET.SubElement(hcloud, 'history')

                # Create new change entry
                change = ET.SubElement(history, 'change')
                change.set('uuid', str(uuid_mod.uuid4()))

                # Add all required fields
                ET.SubElement(change, 'timestamp').text = str(int(time.time()))
                ET.SubElement(change, 'action').text = action
                ET.SubElement(change, 'accountUuid').text = account.get('uuid', '')
                ET.SubElement(change, 'accountName').text = account.get('name', '')
                ET.SubElement(change, 'zoneId').text = entry.get('zoneId', '')
                ET.SubElement(change, 'zoneName').text = entry.get('zoneName', '')
                ET.SubElement(change, 'recordName').text = entry.get('recordName', '')
                ET.SubElement(change, 'recordType').text = entry.get('recordType', '')
                ET.SubElement(change, 'oldValue').text = old_ip or ''
                ET.SubElement(change, 'oldTtl').text = str(entry.get('ttl', 300))
                ET.SubElement(change, 'newValue').text = new_ip or ''
                ET.SubElement(change, 'newTtl').text = str(entry.get('ttl', 300))
                ET.SubElement(change, 'reverted').text = '0'

                # Write back
                f.seek(0)
                f.truncate()
                tree.write(f, encoding='unicode', xml_declaration=True)

                log(f"History: {action} {entry['recordName']}.{entry['zoneName']} "
                    f"{entry['recordType']} {old_ip} -> {new_ip}")
                return True

            finally:
                fcntl.flock(f, fcntl.LOCK_UN)

    except Exception as e:
        log(f"Failed to add history entry: {str(e)}", syslog.LOG_ERR)
        return False


def cleanup_old_history(retention_days):
    """Remove history entries older than retention_days"""
    import fcntl

    if retention_days <= 0:
        return 0

    cutoff_time = int(time.time()) - (retention_days * 86400)
    removed = 0

    try:
        with open(CONFIG_FILE, 'r+') as f:
            fcntl.flock(f, fcntl.LOCK_EX)
            try:
                content = f.read()
                tree = ET.ElementTree(ET.fromstring(content))
                root = tree.getroot()

                hcloud = root.find('.//OPNsense/HCloudDNS')
                if hcloud is None:
                    return 0

                history = hcloud.find('history')
                if history is None:
                    return 0

                # Find entries to remove
                to_remove = []
                for change in history.findall('change'):
                    timestamp = int(change.findtext('timestamp', '0'))
                    if timestamp < cutoff_time:
                        to_remove.append(change)

                # Remove old entries
                for change in to_remove:
                    history.remove(change)
                    removed += 1

                if removed > 0:
                    f.seek(0)
                    f.truncate()
                    tree.write(f, encoding='unicode', xml_declaration=True)
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

        req = urllib.request.Request(url, data=json_data, headers=headers, method=method)

        with urllib.request.urlopen(req, timeout=10):
            log(f"Sent webhook notification: {event_type}")
            return True
    except Exception as e:
        log(f"Failed to send webhook notification: {e}", syslog.LOG_ERR)
        return False


def send_notification(config, event_type, entry, old_ip=None, new_ip=None, error_msg=None):
    """Send notifications for DNS events based on configuration"""
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


def load_config():
    """Load configuration from OPNsense config.xml"""
    config = {
        'enabled': False,
        'checkInterval': 300,
        'failoverEnabled': False,
        'failbackEnabled': True,
        'failbackDelay': 60,
        'verbose': False,
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
                    'healthCheckTarget': gw.findtext('healthCheckTarget', '8.8.8.8')
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
        with open(STATE_FILE, 'w') as f:
            json.dump(state, f, indent=2)
    except IOError as e:
        log(f'Error saving state: {str(e)}', syslog.LOG_ERR)


def check_all_gateways(config, state):
    """Check health and get IPs for all gateways"""
    simulation = load_simulation()

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
                'simulated': False
            }

        gw_state = state['gateways'][uuid]

        # Get current IP
        ip_result = get_gateway_ip(uuid, gw)
        gw_state['ipv4'] = ip_result.get('ipv4')
        gw_state['ipv6'] = ip_result.get('ipv6')

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

        # Determine status based on IP availability
        # (dpinger handles real gateway health via syshook - this is a fallback check)
        has_ip = gw_state['ipv4'] or gw_state['ipv6']
        new_status = 'up' if has_ip else 'down'

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
                log(f"Gateway '{gw['name']}' is DOWN (failCount: {gw_state['failCount']})", syslog.LOG_WARNING)

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


def update_dns_record(api, entry, target_ip, state):
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

        # Only skip if BOTH value AND TTL match
        if current_value == target_ip and current_ttl == ttl:
            return True, 'unchanged'

        # Use the rrsets API to update/create record
        success, message = api.update_record(zone_id, record_name, record_type, target_ip, ttl)

        if success:
            log(f"Updated {record_name}.{entry['zoneName']} {record_type} -> {target_ip}")
            return True, 'updated'
        else:
            log(f"DNS update failed for {record_name}.{entry['zoneName']}: {message}", syslog.LOG_ERR)
            return False, message

    except Exception as e:
        log(f"DNS update failed for {record_name}.{entry['zoneName']}: {str(e)}", syslog.LOG_ERR)
        return False, str(e)


def process_entries(config, state):
    """Process all entries and update DNS as needed"""
    results = {
        'processed': 0,
        'updated': 0,
        'errors': 0,
        'failovers': 0,
        'failbacks': 0,
        'skipped_no_account': 0
    }

    # Cache API instances per account
    api_cache = {}

    for entry in config['entries']:
        if not entry['enabled'] or entry['status'] == 'paused':
            continue

        entry_uuid = entry['uuid']
        account_uuid = entry.get('account', '')

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
        api = api_cache[account_uuid]

        if entry_uuid not in state['entries']:
            state['entries'][entry_uuid] = {
                'hetznerIp': None,
                'lastUpdate': 0,
                'status': 'pending',
                'activeGateway': None
            }

        entry_state = state['entries'][entry_uuid]
        old_active_gw = entry_state.get('activeGateway')

        # Determine active gateway
        active_uuid, active_gw, reason = determine_active_gateway(entry, config, state)

        if not active_gw:
            entry_state['status'] = 'error'
            results['errors'] += 1
            continue

        # Get target IP from gateway
        gw_state = state['gateways'].get(active_uuid, {})
        if entry['recordType'] == 'AAAA':
            target_ip = gw_state.get('ipv6')
        else:
            target_ip = gw_state.get('ipv4')

        if not target_ip:
            log(f"No IP available for entry {entry['recordName']}.{entry['zoneName']}", syslog.LOG_WARNING)
            entry_state['status'] = 'error'
            results['errors'] += 1
            continue

        # Track failover/failback events and send notifications
        failover_event = None
        if old_active_gw and old_active_gw != active_uuid:
            if reason == 'failover':
                results['failovers'] += 1
                state['failoverHistory'].append({
                    'timestamp': int(time.time()),
                    'entry': entry_uuid,
                    'from': old_active_gw,
                    'to': active_uuid,
                    'reason': 'primary_down'
                })
                log(f"FAILOVER: {entry['recordName']}.{entry['zoneName']} switching to failover gateway")
                failover_event = 'failover'
            elif reason == 'failback':
                results['failbacks'] += 1
                state['failoverHistory'].append({
                    'timestamp': int(time.time()),
                    'entry': entry_uuid,
                    'from': old_active_gw,
                    'to': active_uuid,
                    'reason': 'failback'
                })
                log(f"FAILBACK: {entry['recordName']}.{entry['zoneName']} returning to primary gateway")
                failover_event = 'failback'

        entry_state['activeGateway'] = active_uuid

        # Get current Hetzner IP for history tracking
        current_hetzner_ip = entry_state.get('hetznerIp')

        # Update DNS (update_dns_record checks both IP and TTL before deciding to update)
        success, update_reason = update_dns_record(api, entry, target_ip, state)

        if success:
            entry_state['hetznerIp'] = target_ip
            entry_state['lastUpdate'] = int(time.time())
            entry_state['status'] = 'active' if reason in ['primary', 'failback'] else 'failover'
            if update_reason in ['updated', 'created']:
                results['updated'] += 1
                # Add history entry for tracking IP changes
                action = 'create' if update_reason == 'created' else 'update'
                add_history_entry(entry, account, current_hetzner_ip, target_ip, action)
                # Send notification for update
                send_notification(config, 'update', entry, current_hetzner_ip, target_ip)
            # Send failover/failback notification if applicable
            if failover_event:
                send_notification(config, failover_event, entry, current_hetzner_ip, target_ip)
        else:
            entry_state['status'] = 'error'
            results['errors'] += 1
            # Send error notification
            send_notification(config, 'error', entry, error_msg=update_reason)

        results['processed'] += 1

    # Trim failover history to last 100 entries
    if len(state['failoverHistory']) > 100:
        state['failoverHistory'] = state['failoverHistory'][-100:]

    return results


def main():
    result = {
        'status': 'ok',
        'message': '',
        'details': {}
    }

    config = load_config()

    if not config['enabled']:
        result['message'] = 'Service is disabled'
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
    update_results = process_entries(config, state)

    state['lastUpdate'] = int(time.time())
    save_runtime_state(state)

    # Cleanup old history entries
    if config['historyRetentionDays'] > 0:
        cleanup_old_history(config['historyRetentionDays'])

    result['details'] = update_results
    result['message'] = f"Processed {update_results['processed']} entries, {update_results['updated']} updated"

    if update_results.get('skipped_no_account', 0) > 0:
        result['message'] += f", {update_results['skipped_no_account']} skipped (no account)"
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
