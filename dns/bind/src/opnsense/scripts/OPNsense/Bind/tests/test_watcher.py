import collections
import importlib
import ipaddress
import json
import os
import sys
import tempfile
import time
import unittest
from types import SimpleNamespace
from unittest.mock import patch

sys.path.insert(0, "%s/.." % os.path.dirname(__file__))

# Stub runtime-only imports before importing the module under test
sys.modules['daemonize'] = type(sys)('daemonize')
sys.modules['watchers'] = type(sys)('watchers')
sys.modules['watchers.dhcpd'] = type(sys)('watchers.dhcpd')
sys.modules['kea_ctrl'] = type(sys)('kea_ctrl')

import dhcplease_watcher as entrypoint
from dhcpwatcher import config, lease, watcher as watcher_module
from dhcpwatcher.state import StateManager
from dhcpwatcher.updater import BindUpdater, run_nsupdate


class TestWatcherFacadeSurface(unittest.TestCase):
    def test_entry_point_exposes_only_daemon_runtime(self):
        for name in (
            'normalize_isc_lease', 'normalize_kea_lease',
            'build_fqdn', 'build_forward_commands',
            'build_reverse_commands', '_is_valid_hostname',
            'load_config', 'StateManager', 'BindUpdater', 'run_nsupdate',
        ):
            self.assertFalse(hasattr(entrypoint, name), name)

        self.assertFalse(hasattr(watcher_module, 'set_runner_resolver'))
        self.assertFalse(hasattr(watcher_module, 'set_shutdown_checker'))


class TestExtractedWatcherSources(unittest.TestCase):
    def setUp(self):
        self.isc_lease = {
            'ends': time.time() + 3600,
            'binding': 'active',
            'client-hostname': 'isc-host',
            'address': '10.0.0.2',
            'hardware': {'mac-address': 'aa:bb:cc:dd:ee:ff'},
        }
        self.kea_lease = {
            'ip-address': '10.0.0.3',
            'hostname': 'kea-host',
            'hw-address': '11:22:33:44:55:66',
            'cltt': time.time(),
            'valid-lft': 3600,
        }

        class DHCPDLease:
            def __init__(instance, lease_file):
                instance.lease_file = lease_file
                instance.opened = False

            def _open(instance):
                instance.opened = True

            def watch(instance):
                return [self.isc_lease]

        class KeaCtrl:
            @staticmethod
            def send_command(command, arguments, service):
                return {'arguments': {'leases': [self.kea_lease]}}

        sys.modules['watchers.dhcpd'].DHCPDLease = DHCPDLease
        sys.modules['watchers'].dhcpd = sys.modules['watchers.dhcpd']
        sys.modules['kea_ctrl'].KeaCtrl = KeaCtrl
        self.sources = importlib.import_module('dhcpwatcher.sources')

    def test_isc_source_fetches_normalized_leases(self):
        source = self.sources.IscDhcpSource('/tmp/dhcpd.leases')
        expected_lease = lease.normalize_isc_lease(self.isc_lease, 'isc-dhcp')

        self.assertEqual(
            source.fetch_all(),
            {('isc-dhcp', '10.0.0.2'): expected_lease},
        )
        self.assertTrue(source._watcher.opened)

    def test_kea_source_poll_matches_full_fetch(self):
        source = self.sources.KeaDhcp4Source()

        self.assertEqual(source.poll(), source.fetch_all())


class TestExtractedWatcherOrchestration(unittest.TestCase):
    def test_watcher_package_keeps_poll_intervals_and_kea_reconciliation(self):
        watcher_module = importlib.import_module('dhcpwatcher.watcher')
        instance = watcher_module.Watcher(state_path='/tmp/dhcplease-state-test.json')
        expected_changed = object()
        instance.cached_leases = {
            ('isc-dhcp', '10.0.0.2'): {'source': 'isc-dhcp'},
            ('kea-dhcp4', '10.0.0.3'): {'source': 'kea-dhcp4'},
        }
        instance._reconcile = lambda desired: (
            self.assertEqual(
                desired,
                {
                    ('isc-dhcp', '10.0.0.2'): {'source': 'isc-dhcp'},
                },
            ) or expected_changed
        )

        self.assertEqual(watcher_module.Watcher.CLEANUP_INTERVAL, 60)
        self.assertEqual(watcher_module.Watcher.FULL_RECONCILE_INTERVAL, 60)
        self.assertEqual(watcher_module.Watcher.KEA_POLL_INTERVAL, 10)
        self.assertIs(
            instance._handle_kea_poll({}, 'kea-dhcp4'), expected_changed,
        )

class TestBuildFqdn(unittest.TestCase):
    def test_simple(self):
        self.assertEqual(lease.build_fqdn('laptop', 'home.arpa'), 'laptop.home.arpa.')

    def test_already_qualified(self):
        self.assertEqual(lease.build_fqdn('laptop.home.arpa', 'home.arpa'), 'laptop.home.arpa.')

    def test_empty_suffix(self):
        self.assertEqual(lease.build_fqdn('laptop', ''), 'laptop..')


class TestBuildForwardCommands(unittest.TestCase):
    def test_add_ipv4(self):
        addr = ipaddress.IPv4Address('1.2.3.4')
        cmds = lease.forward_commands('add', addr, 'host.example.')
        self.assertEqual(cmds, ['update add host.example. 300 A 1.2.3.4'])

    def test_add_ipv6(self):
        addr = ipaddress.IPv6Address('2001:db8::1')
        cmds = lease.forward_commands('add', addr, 'host.example.')
        self.assertEqual(cmds, ['update add host.example. 300 AAAA 2001:db8::1'])

    def test_delete_ipv4(self):
        addr = ipaddress.IPv4Address('10.0.0.5')
        cmds = lease.forward_commands('delete', addr, 'old.example.')
        self.assertEqual(cmds, ['update delete old.example. 300 A 10.0.0.5'])


class TestBuildReverseCommands(unittest.TestCase):
    def test_add_ipv4(self):
        addr = ipaddress.IPv4Address('1.2.3.4')
        cmds = lease.reverse_commands('add', addr, 'host.example.')
        self.assertEqual(cmds, ['update add 4.3.2.1.in-addr.arpa. 300 PTR host.example.'])

    def test_add_ipv6(self):
        addr = ipaddress.IPv6Address('2001:db8::1')
        cmds = lease.reverse_commands('add', addr, 'host.example.')
        self.assertEqual(cmds, ['update add 1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa. 300 PTR host.example.'])

    def test_delete_ipv4(self):
        addr = ipaddress.IPv4Address('10.0.0.5')
        cmds = lease.reverse_commands('delete', addr, 'old.example.')
        self.assertEqual(cmds, ['update delete 5.0.0.10.in-addr.arpa. 300 PTR old.example.'])


class TestIsValidHostname(unittest.TestCase):
    def test_valid_simple(self):
        self.assertTrue(lease.is_valid_hostname('laptop-01'))

    def test_valid_multilabel(self):
        self.assertTrue(lease.is_valid_hostname('host.sub.domain'))

    def test_invalid_leading_dash(self):
        self.assertFalse(lease.is_valid_hostname('-bad'))

    def test_invalid_trailing_dash(self):
        self.assertFalse(lease.is_valid_hostname('bad-'))

    def test_invalid_underscore(self):
        self.assertFalse(lease.is_valid_hostname('ESP_144844'))

    def test_invalid_empty(self):
        self.assertFalse(lease.is_valid_hostname(''))

    def test_invalid_dot_only(self):
        self.assertFalse(lease.is_valid_hostname('.'))


class TestNormalizeIscLease(unittest.TestCase):
    def test_valid(self):
        isc_lease = {
            'ends': time.time() + 3600,
            'binding': 'active',
            'client-hostname': 'laptop',
            'address': '192.168.1.10',
            'hardware': {'mac-address': 'aa:bb:cc:dd:ee:ff'},
        }
        result = lease.normalize_isc_lease(isc_lease)
        self.assertIsNotNone(result)
        self.assertEqual(result['hostname'], 'laptop')
        self.assertEqual(str(result['address']), '192.168.1.10')
        self.assertEqual(result['source'], 'isc-dhcp')

    def test_expired(self):
        isc_lease = {
            'ends': time.time() - 1,
            'binding': 'active',
            'client-hostname': 'laptop',
            'address': '192.168.1.10',
        }
        self.assertIsNone(lease.normalize_isc_lease(isc_lease))

    def test_free_binding(self):
        isc_lease = {
            'ends': time.time() + 3600,
            'binding': 'free',
            'client-hostname': 'laptop',
            'address': '192.168.1.10',
        }
        self.assertIsNone(lease.normalize_isc_lease(isc_lease))

    def test_invalid_hostname(self):
        isc_lease = {
            'ends': time.time() + 3600,
            'binding': 'active',
            'client-hostname': '-bad',
            'address': '192.168.1.10',
        }
        self.assertIsNone(lease.normalize_isc_lease(isc_lease))

    def test_falsey_client_hostname_is_rejected(self):
        isc_lease = {
            'ends': time.time() + 3600,
            'binding': 'active',
            'address': '192.168.1.10',
        }

        for hostname in (None, False, 0):
            with self.subTest(hostname=hostname):
                invalid_lease = dict(isc_lease, **{'client-hostname': hostname})
                self.assertIsNone(lease.normalize_isc_lease(invalid_lease))


class TestNormalizeKeaLease(unittest.TestCase):
    def test_valid(self):
        kea_lease = {
            'ip-address': '192.168.1.20',
            'hostname': 'desktop',
            'hw-address': '11:22:33:44:55:66',
            'cltt': time.time(),
            'valid-lft': 3600,
            'state': 0,
        }
        result = lease.normalize_kea_lease(kea_lease, 'kea-dhcp4')
        self.assertIsNotNone(result)
        self.assertEqual(result['hostname'], 'desktop')
        self.assertEqual(result['source'], 'kea-dhcp4')

    def test_ia_pd_filtered(self):
        kea_lease = {
            'ip-address': '2001:db8::/64',
            'type': 'IA_PD',
            'hostname': 'router',
            'cltt': time.time(),
            'valid-lft': 3600,
        }
        self.assertIsNone(lease.normalize_kea_lease(kea_lease, 'kea-dhcp6'))

    def test_expired(self):
        kea_lease = {
            'ip-address': '192.168.1.20',
            'hostname': 'desktop',
            'cltt': time.time() - 7200,
            'valid-lft': 3600,
        }
        self.assertIsNone(lease.normalize_kea_lease(kea_lease, 'kea-dhcp4'))

    def test_missing_hostname(self):
        kea_lease = {
            'ip-address': '192.168.1.20',
            'cltt': time.time(),
            'valid-lft': 3600,
        }
        self.assertIsNone(lease.normalize_kea_lease(kea_lease, 'kea-dhcp4'))


class TestStateManagerSerialization(unittest.TestCase):
    def setUp(self):
        self.tmpdir = tempfile.mkdtemp()
        self.state_file = os.path.join(self.tmpdir, 'state.json')

    def tearDown(self):
        if os.path.exists(self.state_file):
            os.unlink(self.state_file)
        os.rmdir(self.tmpdir)

    def test_state_manager_package_contract(self):
        state = {
            StateManager.key('uuid-1', 'isc-dhcp', '10.0.0.5'): {
                'mapping_uuid': 'uuid-1',
                'address': '10.0.0.5',
                'hostname': 'host',
                'suffix': 'example',
                'ends': 1234567890,
                'mac': 'aa:bb:cc:dd:ee:ff',
                'source': 'isc-dhcp',
                'reverse_zone': '',
            }
        }
        state_manager = StateManager(self.state_file)
        self.assertEqual(
            StateManager.key('uuid-1', 'isc-dhcp', '10.0.0.5'),
            'uuid-1|isc-dhcp|10.0.0.5',
        )
        self.assertTrue(state_manager.save(state))
        self.assertEqual(state_manager.load(), state)

        self.assertEqual(
            StateManager.lease_to_state(
                {
                    'address': ipaddress.IPv4Address('10.0.0.5'),
                    'hostname': 'host',
                    'ends': 1234567890,
                    'mac': 'aa:bb:cc:dd:ee:ff',
                    'source': 'isc-dhcp',
                },
                'uuid-1',
                {'hostname_suffix': 'example'},
                None,
            ),
            state[next(iter(state))],
        )

        self.assertTrue(state_manager.save({
            'isc-dhcp,10.0.0.5': {'mapping_uuid': 'uuid-1'},
        }))
        self.assertEqual(state_manager.load(), {})


class TestExtractedWatcherModules(unittest.TestCase):
    def test_config_loader_preserves_mapping_shape_and_defaults(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as config_file:
            config_file.write('[global]\n')
            config_file.write('[reverse-zones]\n')
            config_file.write('10.20.30.0/24 = 30.20.10.in-addr.arpa\n')
            config_file.write('[mapping]\n')
            config_file.write('dhcp_source = isc-dhcp\n')
            config_file.write('lease_subnet = 10.20.30.5/24\n')
            config_file.write('hostname_suffix = home.example\n')
            config_file.write('reverse_zone = 30.20.10.in-addr.arpa\n')
            config_file.write('tsigkey_name = test-key\n')
            config_file.write('tsigkey_algo = hmac-sha256\n')
            config_file.write('tsigkey_secret = c2VjcmV0\n')
            config_path = config_file.name
        try:
            mappings = config.load_config(config_path)
        finally:
            os.unlink(config_path)

        self.assertEqual(
            mappings,
            {
                'mapping': {
                    'dhcp_source': 'isc-dhcp',
                    'lease_subnet': ipaddress.ip_network('10.20.30.0/24'),
                    'hostname_suffix': 'home.example',
                    'reverse_zone': '30.20.10.in-addr.arpa.',
                    'tsigkey_name': 'test-key',
                    'tsigkey_algo': 'hmac-sha256',
                    'tsigkey_secret': 'c2VjcmV0',
                    'nsupdate_address': '127.0.0.1',
                    'nsupdate_port': '53',
                    'reverse_zones': [{
                        'network': ipaddress.ip_network('10.20.30.0/24'),
                        'zone': '30.20.10.in-addr.arpa.',
                    }],
                },
            },
        )

    def test_config_loader_accepts_inferred_ranges_and_subnets(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as config_file:
            config_file.write('[mapping]\n')
            config_file.write('dhcp_source = kea-dhcp4\n')
            config_file.write('lease_scopes = 10.20.30.75-10.20.30.225, 10.20.40.0/24\n')
            config_file.write('hostname_suffix = home.example\n')
            config_file.write('tsigkey_name = test-key\n')
            config_file.write('tsigkey_algo = hmac-sha256\n')
            config_file.write('tsigkey_secret = c2VjcmV0\n')
            config_path = config_file.name
        try:
            mappings = config.load_config(config_path)
        finally:
            os.unlink(config_path)

        self.assertEqual(
            mappings['mapping']['lease_scopes'],
            [
                (ipaddress.ip_address('10.20.30.75'), ipaddress.ip_address('10.20.30.225')),
                ipaddress.ip_network('10.20.40.0/24'),
            ],
        )

    def test_watcher_uses_injected_runner(self):
        mapping = {
            'hostname_suffix': 'home.arpa',
        }
        lease = {
            'address': ipaddress.IPv4Address('10.0.0.5'),
            'hostname': 'printer',
        }
        calls = []
        instance = watcher_module.Watcher(
            state_path='/tmp/dhcplease-state-test.json',
            run_nsupdate_func=lambda current_mapping, commands, zone=None:
            calls.append((commands, zone)) or True,
        )
        self.assertTrue(instance.updater.add_records(mapping, lease))

        self.assertEqual(calls, [
            (['update add printer.home.arpa. 300 A 10.0.0.5'], 'home.arpa'),
        ])


class TestRunNsupdate(unittest.TestCase):
    @patch('dhcpwatcher.updater.subprocess.run')
    def test_run_nsupdate_writes_server_zone_and_send(self, run):
        run.return_value = SimpleNamespace(returncode=0, stderr='')
        mapping = {
            'tsigkey_name': 'test-key', 'tsigkey_algo': 'hmac-sha256',
            'tsigkey_secret': 'c2VjcmV0', 'nsupdate_address': '127.0.0.1',
            'nsupdate_port': '53', 'hostname_suffix': 'home.example',
        }

        self.assertTrue(run_nsupdate(
            mapping,
            ['update add host.home.example. 300 A 10.0.0.2'],
            'home.example',
        ))
        self.assertEqual(run.call_args.args[0][0], '/usr/local/bin/nsupdate')
        self.assertEqual(
            run.call_args.kwargs['input'],
            'server 127.0.0.1 53\n'
            'zone home.example\n'
            'update add host.home.example. 300 A 10.0.0.2\n'
            'send\n',
        )


class TestStateManager(unittest.TestCase):
    def setUp(self):
        self.tmpdir = tempfile.mkdtemp()
        self.state_file = os.path.join(self.tmpdir, 'state.json')
        self.sm = StateManager(self.state_file)

    def tearDown(self):
        if os.path.exists(self.state_file):
            os.unlink(self.state_file)
        os.rmdir(self.tmpdir)

    def test_load_missing(self):
        self.assertEqual(self.sm.load(), {})

    def test_save_and_load(self):
        state = {
            'uuid-1|isc-dhcp|192.168.1.1': {
                'address': '192.168.1.1',
                'hostname': 'router',
                'suffix': 'home.arpa',
                'ends': 1234567890,
                'mac': 'aa:bb:cc:dd:ee:ff',
                'source': 'isc-dhcp',
                'mapping_uuid': 'uuid-1',
                'reverse_zone': '',
            }
        }
        self.sm.save(state)
        loaded = self.sm.load()
        self.assertEqual(loaded, state)

    def test_load_discards_legacy_source_only_state(self):
        self.sm.save({'isc-dhcp,192.168.1.1': {'mapping_uuid': 'uuid-1'}})
        self.assertEqual(self.sm.load(), {})

    def test_mapping_aware_key(self):
        self.assertEqual(
            self.sm.key('uuid-1', 'isc-dhcp', '192.168.1.1'),
            'uuid-1|isc-dhcp|192.168.1.1'
        )


class TestLoadConfig(unittest.TestCase):
    def test_ipv6_reverse_zone_is_parsed_with_its_full_network(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as config_file:
            config_file.write('[reverse-zones]\n')
            config_file.write('fd29:abc0:a250:ab10::/64 = 0.1.b.a.ip6.arpa\n')
            config_file.write('[mapping]\ndhcp_source = isc-dhcp\n')
            config_file.write('lease_scopes = 10.20.30.75-10.20.30.225\n')
            config_file.write('hostname_suffix = home.example\ntsigkey_name = key\n')
            config_file.write('tsigkey_algo = hmac-sha256\ntsigkey_secret = c2VjcmV0\n')
            config_path = config_file.name
        try:
            mappings = config.load_config(config_path)
        finally:
            os.unlink(config_path)

        self.assertEqual(
            mappings['mapping']['reverse_zones'][0]['network'],
            ipaddress.ip_network('fd29:abc0:a250:ab10::/64'),
        )

    def test_reverse_zone_section_is_not_a_mapping(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as config_file:
            config_file.write('[global]\nnsupdate_port = 53\n')
            config_file.write('[reverse-zones]\n10.20.30.0/24 = 30.20.10.in-addr.arpa\n')
            config_file.write('[mapping]\ndhcp_source = isc-dhcp\nlease_subnet = 10.20.30.0/24\n')
            config_file.write('hostname_suffix = home.example\ntsigkey_name = key\n')
            config_file.write('tsigkey_algo = hmac-sha256\ntsigkey_secret = c2VjcmV0\n')
            config_path = config_file.name
        try:
            mappings = config.load_config(config_path)
        finally:
            os.unlink(config_path)
        self.assertEqual(list(mappings), ['mapping'])
        self.assertEqual(mappings['mapping']['reverse_zones'][0]['zone'], '30.20.10.in-addr.arpa.')


class TestWatcherStartupReconcile(unittest.TestCase):
    def setUp(self):
        self.tmpdir = tempfile.mkdtemp()
        self.state_file = os.path.join(self.tmpdir, 'state.json')

    def tearDown(self):
        if os.path.exists(self.state_file):
            os.unlink(self.state_file)
        os.rmdir(self.tmpdir)

    def test_replays_existing_state_after_zone_regeneration(self):
        lease = {
            'address': ipaddress.IPv4Address('192.168.1.10'),
            'hostname': 'laptop',
            'ends': 9999999999,
            'mac': '',
            'source': 'isc-dhcp',
        }

        class Source:
            def fetch_all(self):
                return {('isc-dhcp', '192.168.1.10'): lease}

        class Updater:
            def __init__(self):
                self.added = []
                self.deleted = []

            def add_records(self, mapping, current_lease):
                self.added.append((mapping, current_lease))
                return True

            def delete_records(self, mapping, current_lease):
                self.deleted.append((mapping, current_lease))
                return True

        instance = watcher_module.Watcher(state_path=self.state_file)
        instance.mappings = {
            'mapping-1': {
                'dhcp_source': 'isc-dhcp',
                'lease_subnet': ipaddress.ip_network('192.168.1.0/24'),
                'hostname_suffix': 'home.arpa',
            }
        }
        instance.sources = {'isc-dhcp': Source()}
        instance.updater = Updater()
        instance.state.save({
            'mapping-1|isc-dhcp|192.168.1.10': {
                'address': '192.168.1.10',
                'hostname': 'laptop',
                'suffix': 'home.arpa',
                'ends': 9999999999,
                'mac': '',
                'source': 'isc-dhcp',
                'mapping_uuid': 'mapping-1',
                'reverse_zone': '',
            }
        })

        instance._startup_reconcile()

        self.assertEqual(len(instance.updater.added), 1)
        self.assertEqual(instance.updater.added[0][1], lease)
        self.assertEqual(instance.updater.deleted, [])


class TestWatcherReconcile(unittest.TestCase):
    def setUp(self):
        self.tmpdir = tempfile.mkdtemp()
        self.state_file = os.path.join(self.tmpdir, 'state.json')
        self.lease = {
            'address': ipaddress.IPv4Address('10.20.30.40'),
            'hostname': 'laptop', 'ends': 9999999999, 'mac': '',
            'source': 'isc-dhcp',
        }

    def tearDown(self):
        if os.path.exists(self.state_file):
            os.unlink(self.state_file)
        os.rmdir(self.tmpdir)

    def _watcher(self, updater):
        instance = watcher_module.Watcher(state_path=self.state_file)
        instance.mappings = {
            'home': {
                'dhcp_source': 'isc-dhcp',
                'lease_subnet': ipaddress.ip_network('10.20.30.0/24'),
                'hostname_suffix': 'home.example',
            },
            'iot': {
                'dhcp_source': 'isc-dhcp',
                'lease_subnet': ipaddress.ip_network('10.20.40.0/24'),
                'hostname_suffix': 'iot.example',
            },
        }
        instance.updater = updater
        return instance

    def test_lease_is_published_only_to_its_matching_mapping(self):
        class Updater:
            def __init__(self): self.added = []
            def add_records(self, mapping, lease): self.added.append(mapping['hostname_suffix']); return True
            def delete_records(self, mapping, lease): return True

        updater = Updater()
        instance = self._watcher(updater)
        instance._reconcile({('isc-dhcp', '10.20.30.40'): self.lease})
        self.assertEqual(updater.added, ['home.example'])
        self.assertEqual(list(instance.applied_state), ['home|isc-dhcp|10.20.30.40'])

    def test_inferred_range_matches_isc_lease(self):
        instance = watcher_module.Watcher(state_path=self.state_file)
        instance.mappings = {
            'home': {
                'dhcp_source': 'isc-dhcp',
                'lease_scopes': [
                    (ipaddress.ip_address('10.20.30.75'), ipaddress.ip_address('10.20.30.225')),
                ],
                'hostname_suffix': 'home.example',
            },
        }

        self.assertEqual(
            [uuid for uuid, _mapping in instance._matching_mappings(self.lease)],
            [],
        )
        self.lease['address'] = ipaddress.ip_address('10.20.30.100')
        self.assertEqual(
            [uuid for uuid, _mapping in instance._matching_mappings(self.lease)],
            ['home'],
        )

    def test_inferred_subnet_matches_kea_lease(self):
        instance = watcher_module.Watcher(state_path=self.state_file)
        instance.mappings = {
            'home': {
                'dhcp_source': 'kea-dhcp6',
                'lease_scopes': [ipaddress.ip_network('fd00:20:30::/64')],
                'hostname_suffix': 'home.example',
            },
        }
        lease = dict(self.lease, source='kea-dhcp6', address=ipaddress.ip_address('fd00:20:30::100'))

        self.assertEqual(
            [uuid for uuid, _mapping in instance._matching_mappings(lease)],
            ['home'],
        )

    def test_failed_update_is_not_persisted(self):
        class Updater:
            def add_records(self, mapping, lease): return False
            def delete_records(self, mapping, lease): return True

        instance = self._watcher(Updater())
        instance._reconcile({('isc-dhcp', '10.20.30.40'): self.lease})
        self.assertEqual(instance.applied_state, {})
        self.assertEqual(instance.state.load(), {})

    def test_failed_add_is_retried_and_persisted_only_after_success(self):
        class RetryingUpdater:
            def __init__(self):
                self.add_results = iter([False, True])
                self.add_calls = 0

            def add_records(self, mapping, lease):
                self.add_calls += 1
                return next(self.add_results)

            def delete_records(self, mapping, lease):
                return True

        updater = RetryingUpdater()
        instance = self._watcher(updater)
        leases = {('isc-dhcp', '10.20.30.40'): self.lease}

        instance._reconcile(leases)
        self.assertEqual(instance.applied_state, {})
        self.assertEqual(instance.state.load(), {})

        instance._reconcile(leases)
        self.assertEqual(updater.add_calls, 2)
        self.assertEqual(
            list(instance.applied_state), ['home|isc-dhcp|10.20.30.40']
        )
        self.assertEqual(
            list(instance.state.load()), ['home|isc-dhcp|10.20.30.40']
        )

    def test_failed_delete_remains_persisted_until_retry_succeeds(self):
        class RetryingUpdater:
            def __init__(self):
                self.delete_results = iter([False, True])
                self.delete_calls = 0

            def add_records(self, mapping, lease):
                return True

            def delete_records(self, mapping, lease):
                self.delete_calls += 1
                return next(self.delete_results)

        updater = RetryingUpdater()
        instance = self._watcher(updater)
        key = 'home|isc-dhcp|10.20.30.40'
        entry = StateManager.lease_to_state(
            self.lease, 'home', instance.mappings['home'], None
        )
        instance.applied_state = {key: entry}
        instance.state.save(instance.applied_state)

        instance._reconcile({})
        self.assertEqual(updater.delete_calls, 1)
        self.assertEqual(instance.applied_state, {key: entry})
        self.assertEqual(instance.state.load(), {key: entry})

        instance._reconcile({})
        self.assertEqual(updater.delete_calls, 2)
        self.assertEqual(instance.applied_state, {})
        self.assertEqual(instance.state.load(), {})


class TestBindUpdater(unittest.TestCase):
    def test_add_records_builds_correct_commands(self):
        mapping = {
            'tsigkey_name': 'test-key',
            'tsigkey_algo': 'hmac-sha256',
            'tsigkey_secret': 'c2VjcmV0',
            'nsupdate_address': '127.0.0.1',
            'nsupdate_port': '53',
            'hostname_suffix': 'home.arpa',
        }
        lease = {
            'address': ipaddress.IPv4Address('10.0.0.5'),
            'hostname': 'printer',
        }
        calls = []
        runner = lambda current_mapping, commands, zone=None: calls.append((commands, zone)) or True
        self.assertTrue(BindUpdater(runner).add_records(mapping, lease))

        self.assertEqual(calls, [
            (['update add printer.home.arpa. 300 A 10.0.0.5'], 'home.arpa'),
        ])

    def test_delete_records_builds_correct_commands(self):
        mapping = {
            'hostname_suffix': 'home.arpa',
        }
        lease = {
            'address': '10.0.0.5',
            'hostname': 'printer',
            'suffix': 'home.arpa',
        }
        calls = []
        runner = lambda current_mapping, commands, zone=None: calls.append((commands, zone)) or True
        self.assertTrue(BindUpdater(runner).delete_records(mapping, lease))

        self.assertEqual(calls, [
            (['update delete printer.home.arpa. 300 A 10.0.0.5'], 'home.arpa'),
        ])


if __name__ == '__main__':
    unittest.main()
