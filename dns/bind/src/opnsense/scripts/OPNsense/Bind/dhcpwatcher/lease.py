"""Lease validation and DNS command construction."""

import ipaddress
import re
import syslog
import time


HOSTNAME_PATTERN = re.compile(r"(?!-)[A-Z0-9-]*(?<!-)$", re.IGNORECASE)


def is_valid_hostname(hostname):
    return bool(hostname) and all(
        part and HOSTNAME_PATTERN.match(part)
        for part in hostname.split('.')
    )


def normalize_isc_lease(lease, source='isc-dhcp'):
    if 'ends' not in lease or lease['ends'] <= time.time():
        return None
    if lease.get('binding') in ('free', 'abandoned', 'backup'):
        return None
    if 'address' not in lease:
        return None
    try:
        address = ipaddress.ip_address(lease['address'])
    except ValueError:
        return None
    hostname = lease.get('client-hostname', '')
    if not hostname:
        return None
    hostname = hostname.rstrip('.')
    if not is_valid_hostname(hostname):
        syslog.syslog(
            syslog.LOG_WARNING,
            'dhcpd lease: {} is not a valid hostname, ignoring'.format(hostname),
        )
        return None
    return {
        'address': address, 'hostname': hostname,
        'mac': lease.get('hardware', {}).get('mac-address', '').lower(),
        'ends': lease['ends'], 'source': source,
    }


def normalize_kea_lease(lease, source):
    if lease.get('type') == 'IA_PD':
        return None
    if 'ip-address' not in lease:
        return None
    try:
        address = ipaddress.ip_address(lease['ip-address'])
    except ValueError:
        return None
    hostname = lease.get('hostname', '').rstrip('.')
    if not hostname:
        return None
    if not is_valid_hostname(hostname):
        syslog.syslog(
            syslog.LOG_WARNING,
            'kea lease: {} is not a valid hostname, ignoring'.format(hostname),
        )
        return None
    ends = lease.get('cltt', 0) + lease.get('valid-lft', 0)
    if ends <= time.time():
        return None
    return {
        'address': address, 'hostname': hostname,
        'mac': lease.get('hw-address', '').lower(), 'ends': ends,
        'source': source,
    }


def build_fqdn(hostname, suffix):
    if hostname.endswith('.' + suffix):
        return hostname + '.'
    return hostname + '.' + suffix + '.'


def forward_commands(action, address, fqdn):
    fqdn_name = fqdn.rstrip('.')
    if isinstance(address, ipaddress.IPv4Address):
        return ['update {} {}. 300 A {}'.format(action, fqdn_name, address)]
    if isinstance(address, ipaddress.IPv6Address):
        return ['update {} {}. 300 AAAA {}'.format(action, fqdn_name, address)]
    return []


def reverse_commands(action, address, fqdn):
    fqdn_name = fqdn.rstrip('.')
    return ['update {} {}. 300 PTR {}.'.format(action, address.reverse_pointer, fqdn_name)]


def select_reverse_zone(address, reverse_zones):
    """Return the most-specific configured reverse zone containing address."""
    matches = [zone for zone in reverse_zones if address in zone['network']]
    return max(matches, key=lambda zone: zone['network'].prefixlen) if matches else None
