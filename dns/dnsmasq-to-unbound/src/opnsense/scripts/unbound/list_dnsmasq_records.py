#!/usr/local/bin/python3

"""
    Copyright (c) 2025 C. Hall (chall37@users.noreply.github.com)
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------

    List current DNS records registered from dnsmasq in Unbound.
    Outputs JSON for API consumption.
"""

import argparse
import hashlib
import json
import os
import time
import xml.etree.ElementTree as ET

LEASE_FILE = '/var/db/dnsmasq.leases'
STATIC_HOSTS_FILE = '/var/etc/dnsmasq-hosts'
DNSMASQ_CONF = '/usr/local/etc/dnsmasq.conf'
OPNSENSE_CONFIG = '/conf/config.xml'


def get_config():
    """Load configuration from OPNsense config.xml."""
    config = {
        'enabled': True,
        'watchleases': True,
        'watchstatic': True,
        'domains': []
    }

    if not os.path.exists(OPNSENSE_CONFIG):
        return config

    try:
        tree = ET.parse(OPNSENSE_CONFIG)
        root = tree.getroot()
        node = root.find('.//OPNsense/DnsmasqToUnbound')
        if node is not None:
            for key in ['enabled', 'watchleases', 'watchstatic']:
                elem = node.find(key)
                if elem is not None:
                    config[key] = elem.text == '1'
            domains = node.find('domains')
            if domains is not None and domains.text:
                config['domains'] = [d.strip().lstrip('.') for d in domains.text.split(',') if d.strip()]
    except Exception:
        pass

    return config


def parse_lease_line(line):
    """Parse a dnsmasq lease line."""
    parts = line.strip().split()
    if len(parts) < 4:
        return None
    try:
        expiry = int(parts[0])
    except ValueError:
        return None
    hostname = parts[3] if parts[3] != '*' else None
    if not hostname:
        return None
    return {
        'expiry': expiry,
        'mac': parts[1],
        'ip': parts[2],
        'hostname': hostname
    }


def parse_hosts_line(line):
    """Parse a hosts file line."""
    line = line.strip()
    if not line or line.startswith('#'):
        return None
    parts = line.split()
    if len(parts) < 2:
        return None
    ip = parts[0]
    hostname = parts[1]
    domain = None
    if '.' in hostname:
        parts_name = hostname.split('.', 1)
        hostname = parts_name[0]
        domain = parts_name[1]
    return {'ip': ip, 'hostname': hostname, 'domain': domain}


def get_dhcp_host_macs():
    """Parse dhcp-host entries from dnsmasq.conf to get MAC addresses by IP."""
    mac_by_ip = {}
    if not os.path.exists(DNSMASQ_CONF):
        return mac_by_ip
    try:
        with open(DNSMASQ_CONF, 'r') as f:
            for line in f:
                line = line.strip()
                if line.startswith('dhcp-host='):
                    # Format: dhcp-host=MAC,IP,hostname or dhcp-host=MAC,IP
                    value = line[10:]  # Remove 'dhcp-host='
                    parts = value.split(',')
                    if len(parts) >= 2:
                        mac = parts[0].strip()
                        ip = parts[1].strip()
                        if mac and ip:
                            mac_by_ip[ip] = mac
    except IOError:
        pass
    return mac_by_ip


def get_domains_to_register(domain_filter, source_domain=None):
    """Determine which domains to register a host under."""
    if domain_filter:
        if source_domain and source_domain in domain_filter:
            return [source_domain]
        elif not source_domain:
            return domain_filter
        else:
            return []
    else:
        return [source_domain] if source_domain else ['lan']


def should_replace(existing, new):
    """
    Determine if new record should replace existing record for same FQDN.

    Rules:
    1. If both have expiry timestamps, prefer later expiry (newer lease)
    2. Otherwise, static entries take precedence over leases
    3. If both are same type with no expiry info, keep existing
    """
    existing_expiry = existing.get('expiry_ts')
    new_expiry = new.get('expiry_ts')
    existing_type = existing.get('type')
    new_type = new.get('type')

    # Both have expiry - prefer later expiry (newer)
    if existing_expiry is not None and new_expiry is not None:
        # expiry=0 means infinite, treat as very far future
        existing_cmp = existing_expiry if existing_expiry != 0 else float('inf')
        new_cmp = new_expiry if new_expiry != 0 else float('inf')
        return new_cmp > existing_cmp

    # Static takes precedence over lease when we can't compare timestamps
    if existing_type == 'static' and new_type == 'lease':
        return False
    if existing_type == 'lease' and new_type == 'static':
        return True

    # Same source type, keep existing
    return False


def get_records():
    """Fetch and return deduplicated records."""
    config = get_config()
    domain_filter = config['domains']
    records_by_fqdn = {}  # Deduplicate by FQDN
    current_time = int(time.time())

    # Get MAC addresses from dhcp-host entries
    mac_by_ip = get_dhcp_host_macs()

    # Read static hosts first (they have priority by default)
    if config['watchstatic'] and os.path.exists(STATIC_HOSTS_FILE):
        try:
            with open(STATIC_HOSTS_FILE, 'r') as f:
                for line in f:
                    host = parse_hosts_line(line)
                    if host:
                        for domain in get_domains_to_register(domain_filter, host['domain']):
                            fqdn = f"{host['hostname']}.{domain}"
                            mac = mac_by_ip.get(host['ip'], '-')
                            new_record = {
                                'hostname': host['hostname'],
                                'fqdn': fqdn,
                                'ip': host['ip'],
                                'type': 'static',
                                'mac': mac,
                                'expiry': '-',
                                'expiry_ts': None  # For comparison
                            }
                            # For static duplicates, first one wins
                            if fqdn not in records_by_fqdn:
                                records_by_fqdn[fqdn] = new_record
        except IOError:
            pass

    # Read leases
    if config['watchleases'] and os.path.exists(LEASE_FILE):
        try:
            with open(LEASE_FILE, 'r') as f:
                for line in f:
                    lease = parse_lease_line(line)
                    if lease:
                        if lease['expiry'] != 0 and lease['expiry'] < current_time:
                            continue
                        for domain in get_domains_to_register(domain_filter, None):
                            fqdn = f"{lease['hostname']}.{domain}"
                            new_record = {
                                'hostname': lease['hostname'],
                                'fqdn': fqdn,
                                'ip': lease['ip'],
                                'type': 'lease',
                                'mac': lease['mac'],
                                'expiry': 'infinite' if lease['expiry'] == 0 else time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(lease['expiry'])),
                                'expiry_ts': lease['expiry']  # For comparison
                            }
                            # Handle duplicates with conflict resolution
                            if fqdn in records_by_fqdn:
                                if should_replace(records_by_fqdn[fqdn], new_record):
                                    records_by_fqdn[fqdn] = new_record
                            else:
                                records_by_fqdn[fqdn] = new_record
        except IOError:
            pass

    # Convert to list and remove internal expiry_ts field
    records = []
    for record in records_by_fqdn.values():
        record.pop('expiry_ts', None)
        records.append(record)

    # Sort by FQDN
    records.sort(key=lambda x: (x['fqdn'].lower(), x['ip']))

    return records


def main():
    parser = argparse.ArgumentParser(description='List dnsmasq DNS records')
    parser.add_argument('--hash', action='store_true',
                        help='Output only a hash of the records for change detection')
    args = parser.parse_args()

    records = get_records()

    if args.hash:
        # Generate hash from sorted FQDN list for quick comparison
        fqdns = sorted([r['fqdn'] + ':' + r['ip'] for r in records])
        hash_input = '|'.join(fqdns)
        hash_value = hashlib.md5(hash_input.encode()).hexdigest()
        print(json.dumps({'hash': hash_value}))
    else:
        print(json.dumps({
            'total': len(records),
            'rowCount': len(records),
            'current': 1,
            'rows': records
        }, sort_keys=True))


if __name__ == '__main__':
    main()
