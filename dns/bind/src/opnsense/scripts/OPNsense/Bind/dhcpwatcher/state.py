"""Persistent, mapping-aware state for DHCP records successfully in BIND."""

import json
import os
import syslog
import tempfile


class StateManager:
    """Persist only records whose last requested BIND operation succeeded."""

    def __init__(self, state_file):
        self.state_file = state_file

    @staticmethod
    def key(mapping_uuid, source, address):
        return '{}|{}|{}'.format(mapping_uuid, source, address)

    def load(self):
        if not os.path.isfile(self.state_file):
            return {}
        try:
            with open(self.state_file, 'r') as state_file:
                state = json.load(state_file)
            if not isinstance(state, dict):
                raise ValueError('state is not an object')
            # State from the original watcher was keyed only by source and IP.
            # It cannot safely identify the zone to clean up, so a service
            # reconfigure deliberately rebuilds dynamic records from leases.
            if any('|' not in key or 'mapping_uuid' not in entry for key, entry in state.items()):
                syslog.syslog(syslog.LOG_NOTICE, 'discarding legacy DHCP watcher state')
                return {}
            return state
        except (OSError, ValueError, json.JSONDecodeError) as error:
            syslog.syslog(syslog.LOG_WARNING, 'failed to read watcher state: {}'.format(error))
            return {}

    def save(self, state):
        """Atomically save state and return whether the write succeeded."""
        directory = os.path.dirname(self.state_file)
        temporary = None
        try:
            os.makedirs(directory, exist_ok=True)
            fd, temporary = tempfile.mkstemp(prefix='.dhcplease_state.', dir=directory)
            with os.fdopen(fd, 'w') as state_file:
                json.dump(state, state_file, sort_keys=True)
                state_file.flush()
                os.fsync(state_file.fileno())
            os.replace(temporary, self.state_file)
            return True
        except OSError as error:
            syslog.syslog(syslog.LOG_ERR, 'failed to save watcher state: {}'.format(error))
            if temporary:
                try:
                    os.unlink(temporary)
                except OSError:
                    pass
            return False

    @classmethod
    def record(cls, mapping_uuid, mapping, lease, reverse_zone):
        return {
            'mapping_uuid': mapping_uuid,
            'address': str(lease['address']),
            'hostname': lease['hostname'],
            'suffix': mapping['hostname_suffix'],
            'ends': lease['ends'],
            'mac': lease.get('mac', ''),
            'source': lease['source'],
            'reverse_zone': reverse_zone or '',
        }

    @classmethod
    def lease_to_state(cls, lease, mapping_uuid, mapping, reverse_zone):
        """Return the persisted form of a successfully published lease."""
        return cls.record(mapping_uuid, mapping, lease, reverse_zone)
