#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Gateway health check and IP detection for HCloudDNS
"""

import json
import subprocess
import sys
import os
import socket
import urllib.request
import urllib.error
import tempfile

# State file for gateway status persistence
STATE_FILE = '/var/run/hclouddns_gateways.json'

# IP check services
IP_SERVICES = {
    'web_ipify': {
        'ipv4': 'https://api.ipify.org',
        'ipv6': 'https://api6.ipify.org'
    },
    'web_dyndns': {
        'ipv4': 'http://checkip.dyndns.org',
        'ipv6': None
    },
    'web_freedns': {
        'ipv4': 'https://freedns.afraid.org/dynamic/check.php',
        'ipv6': None
    },
    'web_ip4only': {
        'ipv4': 'https://ip4only.me/api/',
        'ipv6': None
    },
    'web_ip6only': {
        'ipv4': None,
        'ipv6': 'https://ip6only.me/api/'
    }
}


def write_state_file(filepath, content, is_json=True):
    """Atomically write state file with 0600 permissions."""
    fd, tmp = tempfile.mkstemp(dir=os.path.dirname(filepath), prefix='.hclouddns_')
    try:
        with os.fdopen(fd, 'w') as f:
            if is_json:
                json.dump(content, f, indent=2)
            else:
                f.write(content)
        os.chmod(tmp, 0o600)
        os.rename(tmp, filepath)
    except Exception:
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


def load_state():
    """Load gateway state from file"""
    if os.path.exists(STATE_FILE):
        try:
            with open(STATE_FILE, 'r') as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            pass
    return {'gateways': {}, 'lastCheck': 0}


def save_state(state):
    """Save gateway state to file"""
    try:
        write_state_file(STATE_FILE, state)
    except IOError as e:
        sys.stderr.write(f"Error saving state: {e}\n")


def get_interface_ip(interface, ipv6=False):
    """Get IP address from interface using ifconfig"""
    try:
        result = subprocess.run(
            ['ifconfig', interface],
            capture_output=True,
            text=True,
            timeout=5
        )
        if result.returncode == 0:
            for line in result.stdout.split('\n'):
                line = line.strip()
                if ipv6 and line.startswith('inet6 ') and 'scopeid' not in line.lower():
                    parts = line.split()
                    if len(parts) >= 2:
                        addr = parts[1].split('%')[0]
                        if not addr.startswith('fe80:'):
                            return addr
                elif not ipv6 and line.startswith('inet '):
                    parts = line.split()
                    if len(parts) >= 2:
                        return parts[1]
    except (subprocess.TimeoutExpired, subprocess.SubprocessError):
        pass
    return None


def get_web_ip(service, interface=None, source_ip=None, ipv6=False):
    """Get public IP from web service, optionally binding to source IP"""
    service_config = IP_SERVICES.get(service, {})
    url = service_config.get('ipv6' if ipv6 else 'ipv4')

    if not url:
        return None

    try:
        # Use curl if source_ip is specified (more reliable for source binding)
        if source_ip:
            cmd = ['curl', '-s', '--connect-timeout', '10', '--interface', source_ip, url]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
            if result.returncode == 0:
                content = result.stdout.strip()
                if 'dyndns' in service:
                    import re
                    match = re.search(r'(\d+\.\d+\.\d+\.\d+)', content)
                    if match:
                        return match.group(1)
                elif 'ip4only' in service or 'ip6only' in service:
                    parts = content.split(',')
                    if len(parts) >= 2:
                        return parts[1].strip()
                else:
                    if is_valid_ip(content):
                        return content
            return None

        # Default: use urllib without source binding
        request = urllib.request.Request(url, headers={'User-Agent': 'OPNsense-HCloudDNS/2.1'})

        with urllib.request.urlopen(request, timeout=10) as response:
            content = response.read().decode('utf-8').strip()

            if 'dyndns' in service:
                import re
                match = re.search(r'(\d+\.\d+\.\d+\.\d+)', content)
                if match:
                    return match.group(1)
            elif 'ip4only' in service or 'ip6only' in service:
                parts = content.split(',')
                if len(parts) >= 2:
                    return parts[1].strip()
            else:
                if is_valid_ip(content):
                    return content
    except (urllib.error.URLError, socket.timeout, subprocess.TimeoutExpired, Exception) as e:
        sys.stderr.write(f"Error getting IP from {service}: {e}\n")

    return None


def is_valid_ip(ip):
    """Check if string is a valid IP address"""
    try:
        socket.inet_pton(socket.AF_INET, ip)
        return True
    except socket.error:
        try:
            socket.inet_pton(socket.AF_INET6, ip)
            return True
        except socket.error:
            return False


def quick_ping_check(target='8.8.8.8', count=1, timeout=2):
    """
    Quick ping check for gateway connectivity.
    Used as a simple fallback health check.

    Args:
        target: IP or hostname to ping
        count: Number of pings
        timeout: Timeout in seconds

    Returns:
        bool: True if ping succeeded
    """
    cmd = ['ping', '-c', str(count), '-W', str(timeout), target]

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout * count + 2)
        return result.returncode == 0
    except (subprocess.TimeoutExpired, subprocess.SubprocessError):
        return False


def resolve_interface_name(interface):
    """Resolve OPNsense interface name to physical interface and get its IP"""
    # Map common OPNsense names to physical interfaces
    # First try to get from config.xml
    try:
        import xml.etree.ElementTree as ET
        tree = ET.parse('/conf/config.xml')
        root = tree.getroot()
        iface_node = root.find(f'.//interfaces/{interface}')
        if iface_node is not None:
            phys_if = iface_node.findtext('if')
            if phys_if:
                return phys_if
    except Exception:
        pass
    return interface


def get_gateway_ip(uuid, gateway_config):
    """Get current IP for a gateway"""
    interface = gateway_config.get('interface')
    checkip_method = gateway_config.get('checkipMethod', 'web_ipify')

    result = {
        'status': 'ok',
        'uuid': uuid,
        'ipv4': None,
        'ipv6': None
    }

    # Resolve interface name and get local IP for source binding
    phys_interface = resolve_interface_name(interface)
    local_ip = get_interface_ip(phys_interface, ipv6=False)

    if checkip_method == 'if':
        result['ipv4'] = local_ip
        result['ipv6'] = get_interface_ip(phys_interface, ipv6=True)
    else:
        # Use local_ip as source for web requests
        result['ipv4'] = get_web_ip(checkip_method, phys_interface, source_ip=local_ip, ipv6=False)
        result['ipv6'] = get_web_ip(checkip_method, phys_interface, source_ip=None, ipv6=True)

    if not result['ipv4'] and not result['ipv6']:
        result['status'] = 'error'
        result['message'] = 'Could not determine IP address'

    return result


def main():
    """Main entry point for configd actions"""
    if len(sys.argv) < 2:
        print(json.dumps({'status': 'error', 'message': 'No action specified'}))
        sys.exit(1)

    action = sys.argv[1]

    if action == 'healthcheck':
        if len(sys.argv) < 3:
            print(json.dumps({'status': 'error', 'message': 'No gateway UUID specified'}))
            sys.exit(1)

        uuid = sys.argv[2]
        gateway_config = {}
        if len(sys.argv) > 3:
            try:
                gateway_config = json.loads(sys.argv[3])
            except json.JSONDecodeError:
                pass

        # Simple ping-based health check (dpinger handles real gateway monitoring)
        target = gateway_config.get('healthCheckTarget', '8.8.8.8')
        is_healthy = quick_ping_check(target, count=1, timeout=2)
        result = {
            'uuid': uuid,
            'status': 'up' if is_healthy else 'down'
        }
        print(json.dumps(result))

    elif action == 'getip':
        if len(sys.argv) < 3:
            print(json.dumps({'status': 'error', 'message': 'No gateway UUID specified'}))
            sys.exit(1)

        uuid = sys.argv[2]
        gateway_config = {}
        if len(sys.argv) > 3:
            try:
                gateway_config = json.loads(sys.argv[3])
            except json.JSONDecodeError:
                pass

        result = get_gateway_ip(uuid, gateway_config)
        print(json.dumps(result))

    elif action == 'status':
        # Read gateways from OPNsense config and check their status
        result = {'gateways': {}, 'lastCheck': 0}
        try:
            import xml.etree.ElementTree as ET
            import time
            tree = ET.parse('/conf/config.xml')
            root = tree.getroot()

            gateways_node = root.find('.//OPNsense/HCloudDNS/gateways')
            if gateways_node is not None:
                for gw in gateways_node.findall('gateway'):
                    uuid = gw.get('uuid')
                    if not uuid:
                        continue

                    enabled = gw.findtext('enabled', '0')
                    if enabled != '1':
                        continue

                    interface = gw.findtext('interface', '')
                    checkip_method = gw.findtext('checkipMethod', 'web_ipify')
                    health_target = gw.findtext('healthCheckTarget', '8.8.8.8')

                    # Resolve interface and get IP
                    phys_if = resolve_interface_name(interface)
                    ipv4 = None
                    ipv6 = None

                    if checkip_method == 'if':
                        ipv4 = get_interface_ip(phys_if, ipv6=False)
                        ipv6 = get_interface_ip(phys_if, ipv6=True)
                    else:
                        local_ip = get_interface_ip(phys_if, ipv6=False)
                        ipv4 = get_web_ip(checkip_method, phys_if, source_ip=local_ip, ipv6=False)

                    # Quick health check (ping only for speed)
                    status = 'up' if quick_ping_check(health_target, count=1, timeout=2) else 'down'

                    result['gateways'][uuid] = {
                        'status': status,
                        'ipv4': ipv4,
                        'ipv6': ipv6
                    }

            result['lastCheck'] = int(time.time())
        except Exception as e:
            sys.stderr.write(f"Error getting gateway status: {e}\n")

        print(json.dumps(result))

    else:
        print(json.dumps({'status': 'error', 'message': f'Unknown action: {action}'}))
        sys.exit(1)


if __name__ == '__main__':
    main()
