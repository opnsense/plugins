"""Lease reconciliation orchestration for the BIND DHCP watcher."""

import os
import syslog
import time

from dhcpwatcher.config import config_mtime, load_config
from dhcpwatcher.sources import IscDhcpSource, KeaDhcp4Source, KeaDhcp6Source
from dhcpwatcher.state import StateManager
from dhcpwatcher.updater import BindUpdater


def _shutdown_not_requested():
    return False


class Watcher:
    """Orchestrate lease watching and BIND updates."""

    CLEANUP_INTERVAL = 60
    FULL_RECONCILE_INTERVAL = 60
    KEA_POLL_INTERVAL = 10

    def __init__(self, config_path='/usr/local/etc/bind/dhcpwatcher.conf',
                 state_path='/var/cache/bind/dhcplease_state.json',
                 run_nsupdate_func=None, shutdown_checker=None):
        self.config_path = config_path
        self.state = StateManager(state_path)
        self.updater = BindUpdater(run_nsupdate_func)
        self._shutdown_requested = shutdown_checker or _shutdown_not_requested
        self.sources = {}
        self.mappings = {}
        self.cached_leases = {}
        self.applied_state = self.state.load()
        self._config_last_mtime = 0
        self._last_cleanup = 0
        self._last_full_reconcile = 0
        self._pid_misses = 0
        self._kea4_last_poll = 0
        self._kea6_last_poll = 0

    def run(self):
        """Run startup reconciliation followed by the polling loop."""
        self._reload_config()
        if not self.mappings:
            syslog.syslog(syslog.LOG_NOTICE, 'no enabled watcher mappings, exiting')
            return

        self._startup_reconcile()
        syslog.syslog(syslog.LOG_NOTICE, 'entering main loop')
        while not self._shutdown_requested():
            changed = False

            if 'isc-dhcp' in self.sources:
                new_leases = self.sources['isc-dhcp'].poll()
                changed |= self._handle_changes(new_leases, 'isc-dhcp')

            if 'kea-dhcp4' in self.sources:
                if time.time() - self._kea4_last_poll >= self.KEA_POLL_INTERVAL:
                    self._kea4_last_poll = time.time()
                    new_leases = self.sources['kea-dhcp4'].poll()
                    changed |= self._handle_kea_poll(new_leases, 'kea-dhcp4')

            if 'kea-dhcp6' in self.sources:
                if time.time() - self._kea6_last_poll >= self.KEA_POLL_INTERVAL:
                    self._kea6_last_poll = time.time()
                    new_leases = self.sources['kea-dhcp6'].poll()
                    changed |= self._handle_kea_poll(new_leases, 'kea-dhcp6')

            if time.time() - self._last_cleanup > self.CLEANUP_INTERVAL:
                self._last_cleanup = time.time()
                if not self._check_health():
                    return
                self._reload_config_if_changed()
                changed |= self._cleanup_expired()

            if time.time() - self._last_full_reconcile > self.FULL_RECONCILE_INTERVAL:
                self._last_full_reconcile = time.time()
                changed |= self._full_reconcile()

            time.sleep(1)

        syslog.syslog(syslog.LOG_NOTICE, 'watcher exited cleanly')

    def _reload_config(self):
        """Load config and initialize the enabled lease sources."""
        self.mappings = load_config(self.config_path)
        self._config_last_mtime = config_mtime(self.config_path)
        self.sources = {}
        sources_needed = {
            mapping['dhcp_source'] for mapping in self.mappings.values()
        }

        if 'isc-dhcp' in sources_needed:
            try:
                self.sources['isc-dhcp'] = IscDhcpSource()
                syslog.syslog(syslog.LOG_NOTICE, 'watching ISC DHCP leases')
            except IOError:
                syslog.syslog(syslog.LOG_WARNING, 'cannot open ISC DHCP lease file')

        if 'kea-dhcp4' in sources_needed:
            try:
                self.sources['kea-dhcp4'] = KeaDhcp4Source()
            except Exception as error:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    'cannot initialize Kea DHCPv4 source: {}'.format(error),
                )

        if 'kea-dhcp6' in sources_needed:
            try:
                self.sources['kea-dhcp6'] = KeaDhcp6Source()
            except Exception as error:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    'cannot initialize Kea DHCPv6 source: {}'.format(error),
                )

    def _reload_config_if_changed(self):
        new_mtime = config_mtime(self.config_path)
        if new_mtime != self._config_last_mtime:
            syslog.syslog(syslog.LOG_NOTICE, 'config file changed, reloading')
            self._reload_config()

    def _matching_mappings(self, lease):
        """Yield mappings whose source and subnet contain the lease."""
        for uuid, mapping in self.mappings.items():
            if mapping['dhcp_source'] != lease['source']:
                continue
            if 'lease_scopes' in mapping:
                matches_scope = any(
                    self._scope_contains(scope, lease['address'])
                    for scope in mapping['lease_scopes']
                )
            else:
                matches_scope = lease['address'] in mapping['lease_subnet']
            if matches_scope:
                yield uuid, mapping

    @staticmethod
    def _scope_contains(scope, address):
        if isinstance(scope, tuple):
            start, end = scope
            return start <= address <= end
        return address in scope

    def _startup_reconcile(self):
        """Replay current leases without marking failed updates as applied."""
        syslog.syslog(syslog.LOG_NOTICE, 'starting lease reconciliation')
        self.applied_state = self.state.load()
        self._reconcile(self._fetch_all(), replay=True)

    def _full_reconcile(self):
        """Periodically retry failed operations and correct drift."""
        syslog.syslog(syslog.LOG_NOTICE, 'running full reconcile')
        return self._reconcile(self._fetch_all())

    def _fetch_all(self):
        desired = {}
        for source in self.sources.values():
            desired.update(source.fetch_all())
        return desired

    def _desired_records(self, leases):
        desired = {}
        for lease in leases.values():
            for uuid, mapping in self._matching_mappings(lease):
                reverse_zone = BindUpdater._reverse_zone(mapping, lease['address'])
                key = StateManager.key(uuid, lease['source'], str(lease['address']))
                desired[key] = (uuid, mapping, lease, reverse_zone)
        return desired

    @staticmethod
    def _state_matches(entry, lease, mapping, reverse_zone):
        return (
            entry['hostname'] == lease['hostname']
            and entry['suffix'] == mapping['hostname_suffix']
            and entry.get('reverse_zone', '') == (reverse_zone or '')
        )

    def _reconcile(self, leases, replay=False):
        """Converge BIND and persisted state while retaining failed operations."""
        desired = self._desired_records(leases)
        changed = False

        for key, entry in list(self.applied_state.items()):
            if key in desired:
                continue
            mapping = self.mappings.get(entry['mapping_uuid'])
            if mapping is None:
                del self.applied_state[key]
                changed = True
            elif self.updater.delete_records(mapping, entry):
                del self.applied_state[key]
                changed = True

        for key, (uuid, mapping, lease, reverse_zone) in desired.items():
            previous = self.applied_state.get(key)
            if previous and not replay and self._state_matches(
                    previous, lease, mapping, reverse_zone):
                continue
            if previous and not replay and not self.updater.delete_records(
                    mapping, previous):
                continue
            if self.updater.add_records(mapping, lease):
                self.applied_state[key] = StateManager.lease_to_state(
                    lease, uuid, mapping, reverse_zone
                )
                changed = True

        self.cached_leases = leases
        if changed:
            self.state.save(self.applied_state)
        return changed

    def _handle_changes(self, new_leases, source_name):
        """Handle new or changed event-driven ISC lease updates."""
        if not new_leases:
            return False
        desired = dict(self.cached_leases)
        desired.update(new_leases)
        return self._reconcile(desired)

    def _handle_kea_poll(self, new_leases, source_name):
        """Handle full Kea polls, including source-specific lease removals."""
        desired = {
            key: lease for key, lease in self.cached_leases.items()
            if key[0] != source_name
        }
        desired.update(new_leases)
        return self._reconcile(desired)

    def _cleanup_expired(self):
        """Remove expired leases from cache and BIND."""
        now = time.time()
        desired = {
            key: lease for key, lease in self.cached_leases.items()
            if lease['ends'] >= now
        }
        return self._reconcile(desired)

    def _check_health(self):
        """Return false after two consecutive failed named PID checks."""
        try:
            with open('/var/run/named/pid', 'r') as pid_file:
                os.kill(int(pid_file.read().strip()), 0)
            is_running = True
        except (OSError, ValueError):
            is_running = False

        if not is_running:
            self._pid_misses += 1
            if self._pid_misses >= 2:
                syslog.syslog(syslog.LOG_NOTICE, 'named not running, exiting')
                return False
        else:
            self._pid_misses = 0
        return True
