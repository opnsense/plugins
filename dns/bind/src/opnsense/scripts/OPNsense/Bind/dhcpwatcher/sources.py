"""DHCP lease-source adapters for the BIND watcher."""

import sys
import syslog

from dhcpwatcher import lease as lease_helpers


class IscDhcpSource:
    """Tail the ISC dhcpd.leases file for new or changed leases."""

    def __init__(self, lease_file='/var/dhcpd/var/db/dhcpd.leases'):
        # Lazy import so the appliance-only watcher dependency is optional.
        sys.path.insert(0, '/usr/local/opnsense/site-python')
        import watchers.dhcpd
        self._watcher = watchers.dhcpd.DHCPDLease(lease_file)
        self._source_label = 'isc-dhcp'

    def fetch_all(self):
        """Read the complete ISC lease file."""
        self._watcher._open()
        return self._normalize(self._watcher.watch())

    def poll(self):
        """Return ISC leases appended since the prior poll."""
        return self._normalize(self._watcher.watch())

    def _normalize(self, records):
        leases = {}
        for record in records:
            normalized = lease_helpers.normalize_isc_lease(
                record, self._source_label
            )
            if normalized:
                leases[(normalized['source'], str(normalized['address']))] = normalized
        return leases


class _KeaDhcpSource:
    """Shared implementation for Kea DHCP lease sources."""

    command = None
    service = None
    source_label = None
    log_label = None

    def __init__(self):
        sys.path.insert(0, '/usr/local/opnsense/scripts/kea/lib')
        from kea_ctrl import KeaCtrl
        self._ctrl = KeaCtrl

    def fetch_all(self):
        leases = {}
        try:
            result = self._ctrl.send_command(self.command, {}, self.service)
            for record in result.get('arguments', {}).get('leases', []):
                normalized = lease_helpers.normalize_kea_lease(
                    record, self.source_label
                )
                if normalized:
                    leases[(normalized['source'], str(normalized['address']))] = normalized
        except Exception as error:
            syslog.syslog(
                syslog.LOG_WARNING,
                '{} fetch failed: {}'.format(self.log_label, error),
            )
        return leases

    def poll(self):
        return self.fetch_all()


class KeaDhcp4Source(_KeaDhcpSource):
    """Poll Kea DHCPv4 through its control socket."""

    command = 'lease4-get-all'
    service = 'dhcp4'
    source_label = 'kea-dhcp4'
    log_label = 'kea4'


class KeaDhcp6Source(_KeaDhcpSource):
    """Poll Kea DHCPv6 through its control socket."""

    command = 'lease6-get-all'
    service = 'dhcp6'
    source_label = 'kea-dhcp6'
    log_label = 'kea6'
