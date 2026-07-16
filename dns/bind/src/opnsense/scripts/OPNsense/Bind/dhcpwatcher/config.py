"""Watcher configuration loading."""

import configparser
import ipaddress
import os
import syslog


def parse_lease_scopes(value):
    """Parse comma-separated DHCP ranges or CIDR networks."""
    scopes = []
    for scope in value.split(','):
        scope = scope.strip()
        if not scope:
            continue
        if '-' in scope:
            start, end = (ipaddress.ip_address(address.strip()) for address in scope.split('-', 1))
            if start.version != end.version or start > end:
                raise ValueError('invalid DHCP lease range: {}'.format(scope))
            scopes.append((start, end))
        else:
            scopes.append(ipaddress.ip_network(scope, strict=False))
    if not scopes:
        raise ValueError('no DHCP lease scopes configured')
    return scopes


def load_config(path='/usr/local/etc/bind/dhcpwatcher.conf'):
    """Return mapping UUIDs keyed to the current watcher configuration shape."""
    mappings = {}
    reverse_zones = []
    if not os.path.isfile(path):
        syslog.syslog(syslog.LOG_NOTICE, 'config file not found: {}'.format(path))
        return mappings

    cnf = configparser.ConfigParser(delimiters=('='), interpolation=None)
    cnf.read(path)

    nsupdate_address = '127.0.0.1'
    nsupdate_port = '53'
    if cnf.has_section('global'):
        if cnf.has_option('global', 'nsupdate_address'):
            nsupdate_address = cnf.get('global', 'nsupdate_address')
        if cnf.has_option('global', 'nsupdate_port'):
            nsupdate_port = cnf.get('global', 'nsupdate_port')

    if cnf.has_section('reverse-zones'):
        for subnet, zone in cnf.items('reverse-zones'):
            try:
                reverse_zones.append({
                    'network': ipaddress.ip_network(subnet, strict=False),
                    'zone': zone.rstrip('.') + '.',
                })
            except ValueError:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    'ignoring invalid reverse zone subnet: {}'.format(subnet),
                )

    for section in cnf.sections():
        if section in ('global', 'reverse-zones'):
            continue
        try:
            lease_scopes = parse_lease_scopes(cnf.get(section, 'lease_scopes')) \
                if cnf.has_option(section, 'lease_scopes') else None
            lease_subnet = ipaddress.ip_network(
                cnf.get(section, 'lease_subnet'), strict=False
            ) if lease_scopes is None else None
            reverse_zone = cnf.get(section, 'reverse_zone', fallback='').rstrip('.')
            mappings[section] = {
                'dhcp_source': cnf.get(section, 'dhcp_source'),
                'hostname_suffix': cnf.get(section, 'hostname_suffix'),
                'reverse_zone': reverse_zone + '.' if reverse_zone else '',
                'tsigkey_name': cnf.get(section, 'tsigkey_name'),
                'tsigkey_algo': cnf.get(section, 'tsigkey_algo'),
                'tsigkey_secret': cnf.get(section, 'tsigkey_secret'),
                'nsupdate_address': nsupdate_address,
                'nsupdate_port': nsupdate_port,
                'reverse_zones': reverse_zones,
            }
            if lease_scopes is not None:
                mappings[section]['lease_scopes'] = lease_scopes
            else:
                mappings[section]['lease_subnet'] = lease_subnet
        except (configparser.Error, ValueError) as error:
            syslog.syslog(
                syslog.LOG_WARNING,
                'ignoring invalid watcher mapping {}: {}'.format(section, error),
            )
    return mappings


def config_mtime(path='/usr/local/etc/bind/dhcpwatcher.conf'):
    """Return mtime or zero when the config file is absent."""
    try:
        return os.stat(path).st_mtime
    except OSError:
        return 0
