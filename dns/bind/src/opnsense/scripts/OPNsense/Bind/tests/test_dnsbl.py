import importlib.util
import json
import os
import pathlib
import subprocess
import tempfile
import time
import unittest


SPEC = importlib.util.spec_from_file_location(
    'dnsbl', os.path.join(os.path.dirname(__file__), '..', 'dnsbl.py')
)
dnsbl = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(dnsbl)


OPNSENSE_ROOT = pathlib.Path(__file__).resolve().parents[4]


class TestNormalizeDomains(unittest.TestCase):
    def test_status_records_normalized_domain_and_rpz_counts(self):
        with tempfile.TemporaryDirectory() as directory:
            status_path = os.path.join(directory, 'dnsbl-status.json')
            dnsbl.write_status(status_path, 'fetched', domains=123, inc_bytes=456)
            with open(status_path) as status_file:
                status = json.load(status_file)

        self.assertEqual(status['stage'], 'fetched')
        self.assertEqual(status['domains'], 123)
        self.assertEqual(status['rpz_records'], 246)
        self.assertEqual(status['inc_bytes'], 456)
        self.assertEqual(status['estimated_peak_kb'], 123)
        self.assertIn('updated_at', status)
    def test_accepts_hosts_plain_and_adblock_domains(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as raw:
            raw.write('0.0.0.0 ads.example\nplain.example\n||tracker.example^\n')
            path = raw.name
        try:
            self.assertEqual(
                dnsbl.normalize_domains(path),
                {'ads.example', 'plain.example', 'tracker.example'}
            )
        finally:
            os.unlink(path)

    def test_accepts_hagezi_wildcard_domains(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as raw:
            raw.write('*.instagram.com\n')
            path = raw.name
        try:
            self.assertEqual(dnsbl.normalize_domains(path), {'instagram.com'})
        finally:
            os.unlink(path)

    def test_rejects_non_dns_owner_names(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as raw:
            raw.write('||invalid owner^\n/regex/\nlocalhost\n')
            path = raw.name
        try:
            self.assertEqual(dnsbl.normalize_domains(path), set())
        finally:
            os.unlink(path)

    def test_refresh_actions_reload_the_rpz_zone_and_flush_cache(self):
        actions = (OPNSENSE_ROOT / 'service/conf/actions.d/actions_bind.conf').read_text()
        refresh_script = OPNSENSE_ROOT / 'scripts/OPNsense/Bind/dnsblRefresh.sh'

        self.assertEqual(
            actions.count('command:/usr/local/opnsense/scripts/OPNsense/Bind/dnsblRefresh.sh'),
            2,
        )
        script = refresh_script.read_text()
        self.assertIn('DNSBL_SCRIPT="/usr/local/opnsense/scripts/OPNsense/Bind/dnsbl.py"', script)
        self.assertIn('"$DNSBL_SCRIPT" "$@"', script)
        self.assertIn('rndc reload blacklist.localdomain', script)
        self.assertIn('rndc flush', script)

    def test_apply_action_fetches_before_starting_bind(self):
        actions = (OPNSENSE_ROOT / 'service/conf/actions.d/actions_bind.conf').read_text()
        apply_script = OPNSENSE_ROOT / 'scripts/OPNsense/Bind/dnsblApply.sh'
        service = (
            OPNSENSE_ROOT
            / 'mvc/app/controllers/OPNsense/Bind/Api/ServiceController.php'
        ).read_text()

        self.assertIn('[dnsblapply]', actions)
        self.assertTrue(apply_script.is_file())
        script = apply_script.read_text()
        self.assertLess(
            script.index('dnsbl.py'),
            script.index('"${DNSBL_APPLY_PLUGINCTL}" -c bind_start'),
        )
        self.assertIn("dnsblApplyAction", service)

    def test_apply_action_is_dispatched_without_waiting_for_dnsbl_startup(self):
        service = (
            OPNSENSE_ROOT
            / 'mvc/app/controllers/OPNsense/Bind/Api/ServiceController.php'
        ).read_text()

        self.assertIn(
            "configdpRun('bind dnsblapply', [(string)$mdl->type], true)",
            service,
        )

    def test_apply_script_serializes_the_full_dnsbl_operation(self):
        apply_script = (
            OPNSENSE_ROOT / 'scripts/OPNsense/Bind/dnsblApply.sh'
        ).read_text()

        self.assertIn('DNSBL_APPLY_LOCK_DIR=', apply_script)
        self.assertIn('mkdir "${DNSBL_APPLY_LOCK_DIR}"', apply_script)
        self.assertIn('trap release_lock EXIT HUP INT TERM', apply_script)
        self.assertIn('DNSBL_APPLY_STATUS_SCRIPT', apply_script)
        self.assertIn('"${DNSBL_APPLY_STATUS_SCRIPT}" --stage', apply_script)
        self.assertIn('dnsbl_active|guard_recovered|disabled|failed', apply_script)

    def test_apply_script_rejects_a_second_operation_while_the_first_holds_lock(self):
        apply_script = OPNSENSE_ROOT / 'scripts/OPNsense/Bind/dnsblApply.sh'
        with tempfile.TemporaryDirectory(dir=OPNSENSE_ROOT) as directory:
            temp_path = pathlib.Path(directory)
            bin_path = temp_path / 'bin'
            bin_path.mkdir()
            events = temp_path / 'events'
            self._script(bin_path / 'dnsbl', 'printf "dnsbl\\n" >> "$TEST_EVENTS"\nsleep 1')
            self._script(
                bin_path / 'status',
                'if [ "$1" = "--stage" ]; then echo disabled; else printf "status\\n" >> "$TEST_EVENTS"; fi',
            )
            self._script(bin_path / 'pluginctl', 'printf "pluginctl\\n" >> "$TEST_EVENTS"')
            self._script(bin_path / 'logger', 'printf "logger\\n" >> "$TEST_EVENTS"')
            environment = os.environ | {
                'DNSBL_APPLY_DNSBL_SCRIPT': str(bin_path / 'dnsbl'),
                'DNSBL_APPLY_STATUS_SCRIPT': str(bin_path / 'status'),
                'DNSBL_APPLY_PLUGINCTL': str(bin_path / 'pluginctl'),
                'DNSBL_APPLY_LOGGER': str(bin_path / 'logger'),
                'DNSBL_APPLY_LOCK_DIR': str(temp_path / 'apply.lock'),
                'TEST_EVENTS': str(events),
            }
            first = subprocess.Popen([apply_script], env=environment)
            try:
                time.sleep(0.1)
                second = subprocess.run([apply_script], env=environment, check=False)
                self.assertEqual(second.returncode, 0)
            finally:
                self.assertEqual(first.wait(), 0)
            self.assertEqual(events.read_text().splitlines().count('dnsbl'), 1)
            self.assertEqual(events.read_text().splitlines().count('pluginctl'), 1)
            self.assertEqual(events.read_text().splitlines().count('logger'), 1)

    @staticmethod
    def _script(path, body):
        path.write_text(f'#!/bin/sh\n{body}\n')
        path.chmod(0o755)

    def test_dnsbl_page_reattaches_to_persisted_operation_status_after_refresh(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn('if (data.stage !== "idle")', view)
        self.assertIn('updateDnsblOperation();', view)

    def test_dnsbl_page_disables_save_while_an_operation_is_active(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn('function isDnsblOperationActive(stage)', view)
        self.assertIn('$("#saveAct_dnsbl").prop("disabled", isDnsblOperationActive(data.stage));', view)
        self.assertIn('$("#saveAct_dnsbl").prop("disabled", true);', view)

    def test_dnsbl_page_waits_for_a_status_update_from_the_current_save(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn('let dnsblRequestStartedAt = null;', view)
        self.assertIn('dnsblRequestStartedAt = Date.now() / 1000;', view)
        self.assertIn('data.updated_at !== undefined &&', view)
        self.assertIn('data.updated_at >= dnsblRequestStartedAt', view)
        self.assertIn('showDnsblFetching();', view)

    def test_dnsbl_page_uses_a_rolling_ellipsis_while_fetching(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn('function showDnsblFetching()', view)
        self.assertIn('".".repeat((Math.floor(Date.now() / 1000) % 3) + 1)', view)
        self.assertIn('if (data.stage === "fetching") {', view)

    def test_dnsbl_page_reports_the_dnsbl_result_without_bind_process_state(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn('"<br>dnsbl.inc: " + mib + " MiB<br>Estimated startup peak: "', view)
        self.assertNotIn('BIND: " + (data.named_running', view)

    def test_starting_status_records_a_guard_start_time_for_refreshes(self):
        status_script = (
            OPNSENSE_ROOT / 'scripts/OPNsense/Bind/dnsblStatus.py'
        ).read_text()

        self.assertIn('import time', status_script)
        self.assertIn("if status['stage'] == 'starting':", status_script)
        self.assertIn("status['guard_started_at'] = int(time.time())", status_script)

    def test_dnsbl_page_counts_down_from_the_persisted_guard_start_time(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn(
            'const remainingSeconds = Math.max(0, 90 - Math.floor(',
            view,
        )
        self.assertIn(
            '"DNSBL starting; " + remainingSeconds + " seconds remaining..."',
            view,
        )

    def test_guard_recovery_status_has_an_insufficient_ram_heading(self):
        view = (
            OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/general.volt'
        ).read_text()

        self.assertIn(
            '"DNSBL guard_recovered: insufficient ram"',
            view,
        )
