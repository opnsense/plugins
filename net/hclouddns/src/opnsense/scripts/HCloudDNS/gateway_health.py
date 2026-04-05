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


HETZNER_NAMESERVERS = [
    '213.133.100.98',   # hydrogen.ns.hetzner.com
    '88.198.229.192',   # oxygen.ns.hetzner.com
    '193.47.99.3',      # helium.ns.hetzner.de
]


def verify_dns_propagation(record_name, zone_name, record_type, expected_ip,
                           nameservers=None, timeout=5):
    """Query authoritative Hetzner nameservers to verify DNS propagation.

    Uses dnspython (available on OPNsense via py-dnspython) for direct queries.
    Falls back to drill (FreeBSD) if dnspython is not available.

    Returns:
        dict with 'propagated' (bool), 'results' (ns->ip), 'errors' (ns->error)
    """
    if nameservers is None:
        nameservers = HETZNER_NAMESERVERS

    fqdn = f"{record_name}.{zone_name}" if record_name != '@' else zone_name

    results = {}
    errors = {}

    # Try dnspython first (preferred, available on OPNsense)
    try:
        import dns.resolver
        import dns.rdatatype

        rdtype = dns.rdatatype.from_text(record_type)

        for ns in nameservers:
            try:
                resolver = dns.resolver.Resolver(configure=False)
                resolver.nameservers = [ns]
                resolver.lifetime = timeout

                answer = resolver.resolve(fqdn, rdtype)
                for rdata in answer:
                    results[ns] = str(rdata)
                    break  # first answer
            except dns.resolver.NXDOMAIN:
                errors[ns] = 'NXDOMAIN'
            except dns.resolver.NoAnswer:
                errors[ns] = 'no answer'
            except dns.resolver.NoNameservers:
                errors[ns] = 'no nameservers'
            except dns.exception.Timeout:
                errors[ns] = 'timeout'
            except Exception as e:
                errors[ns] = str(e)

    except ImportError:
        # Fallback to drill (available on FreeBSD/OPNsense via ldns)
        for ns in nameservers:
            try:
                cmd = ['drill', f'@{ns}', fqdn, record_type]
                proc = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout + 5)
                if proc.returncode == 0:
                    # Parse drill output: look for answer section
                    in_answer = False
                    for line in proc.stdout.splitlines():
                        if line.strip() == ';; ANSWER SECTION:':
                            in_answer = True
                            continue
                        if in_answer and line.strip() and not line.startswith(';;'):
                            parts = line.split()
                            if len(parts) >= 5:
                                results[ns] = parts[-1]
                                break
                        elif in_answer and (line.startswith(';;') or not line.strip()):
                            break
                    if ns not in results:
                        errors[ns] = 'empty response'
                else:
                    errors[ns] = proc.stderr.strip() or 'drill failed'
            except subprocess.TimeoutExpired:
                errors[ns] = 'timeout'
            except Exception as e:
                errors[ns] = str(e)

    propagated = any(ip == expected_ip for ip in results.values())

    return {
        'propagated': propagated,
        'results': results,
        'errors': errors
    }


def get_opnsense_gateway_status():
    """Query OPNsense's dpinger-based gateway status and gateway-to-interface mapping.

    Returns a dict mapping OPNsense interface name (e.g. 'wan', 'opt1') to status string.
    OPNsense status values: 'none' = online, 'down', 'force_down', 'loss', 'delay', etc.
    """
    iface_status = {}
    try:
        # Get gateway details with interface mapping
        gw_details = subprocess.run(
            ['php', '-r', """
require_once 'config.inc';
require_once 'util.inc';
require_once 'interfaces.inc';
require_once 'plugins.inc.d/dpinger.inc';
$status = dpinger_status();
$gws = (new \\OPNsense\\Routing\\Gateways())->gatewaysIndexedByName();
$result = [];
foreach ($gws as $name => $gw) {
    $s = isset($status[$name]) ? strtolower($status[$name]['status']) : 'none';
    $iface = isset($gw['interface']) ? $gw['interface'] : '';
    $proto = isset($gw['ipprotocol']) ? $gw['ipprotocol'] : 'inet';
    $result[] = ['name' => $name, 'interface' => $iface, 'ipprotocol' => $proto, 'status' => $s];
}
echo json_encode($result);
"""],
            capture_output=True, text=True, timeout=10
        )
        if gw_details.returncode == 0 and gw_details.stdout.strip():
            gateways = json.loads(gw_details.stdout)
            for gw in gateways:
                iface = gw.get('interface', '')
                proto = gw.get('ipprotocol', 'inet')
                status = gw.get('status', 'none')
                if not iface:
                    continue
                # Only use inet (IPv4) gateways for status matching
                # (avoid overwriting with inet6 status for same interface)
                if proto == 'inet':
                    iface_status[iface] = status
                elif iface not in iface_status:
                    iface_status[iface] = status
    except (subprocess.TimeoutExpired, subprocess.SubprocessError, json.JSONDecodeError) as e:
        sys.stderr.write(f"Error querying OPNsense gateway status: {e}\n")
    return iface_status


def is_gateway_up(interface, opnsense_status):
    """Check if a gateway is up based on OPNsense's dpinger status for its interface.

    OPNsense reports status='none' for healthy gateways.
    Any other value (force_down, down, loss, delay, etc.) means degraded/down.
    """
    status = opnsense_status.get(interface)
    if status is None:
        # Interface not found in OPNsense gateways — assume up
        return True
    return status == 'none'


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

        interface = gateway_config.get('interface', '')
        opnsense_status = get_opnsense_gateway_status()
        is_healthy = is_gateway_up(interface, opnsense_status)
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

            # Query OPNsense's own gateway status once for all gateways
            opnsense_status = get_opnsense_gateway_status()

            gateways_node = root.find('.//OPNsense/HCloudDNS/gateways')
            if gateways_node is not None:
                for gw in gateways_node.findall('gateway'):
                    uuid = gw.get('uuid')
                    if not uuid:
                        continue

                    enabled = gw.findtext('enabled', '0')
                    if enabled != '1':
                        continue

                    name = gw.findtext('name', '')
                    interface = gw.findtext('interface', '')
                    checkip_method = gw.findtext('checkipMethod', 'web_ipify')

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

                    # Use OPNsense's dpinger-based gateway status (matched by interface)
                    status = 'up' if is_gateway_up(interface, opnsense_status) else 'down'

                    result['gateways'][uuid] = {
                        'status': status,
                        'ipv4': ipv4,
                        'ipv6': ipv6
                    }

            result['lastCheck'] = int(time.time())
        except Exception as e:
            sys.stderr.write(f"Error getting gateway status: {e}\n")

        print(json.dumps(result))

    elif action == 'propagation':
        if len(sys.argv) < 6:
            print(json.dumps({'status': 'error',
                              'message': 'Usage: propagation <record_name> <zone_name> <record_type> <expected_ip>'}))
            sys.exit(1)

        record_name = sys.argv[2]
        zone_name = sys.argv[3]
        record_type = sys.argv[4]
        expected_ip = sys.argv[5]

        result = verify_dns_propagation(record_name, zone_name, record_type, expected_ip)
        result['status'] = 'ok'
        print(json.dumps(result))

    else:
        print(json.dumps({'status': 'error', 'message': f'Unknown action: {action}'}))
        sys.exit(1)


if __name__ == '__main__':
    main()
