import os
import pathlib
import subprocess
import tempfile
import unittest


class BindStartMemoryGuardTest(unittest.TestCase):
    def setUp(self):
        self.bind_root = pathlib.Path(__file__).resolve().parents[6]
        self.guard = (
            self.bind_root
            / "src/opnsense/scripts/OPNsense/Bind/namedMemoryGuard.sh"
        )
        self.recovery = (
            self.bind_root
            / "src/opnsense/scripts/OPNsense/Bind/dnsblMemoryRecovery.sh"
        )
        self.disable_script = (
            self.bind_root
            / "src/opnsense/scripts/OPNsense/Bind/dnsblDisableOnMemoryGuard.php"
        )

    def test_dnsbl_form_warns_about_large_memory_requirements(self):
        form = (
            self.bind_root
            / "src/opnsense/mvc/app/controllers/OPNsense/Bind/forms/dnsbl.xml"
        ).read_text()

        self.assertIn(
            "Large DNSBL selections may require gigabytes of RAM while BIND loads the response-policy zone.",
            form,
        )

    def test_memory_guard_is_configured_in_mib_with_a_safe_default(self):
        model = (
            self.bind_root / "src/opnsense/mvc/app/models/OPNsense/Bind/Dnsbl.xml"
        ).read_text()
        form = (
            self.bind_root
            / "src/opnsense/mvc/app/controllers/OPNsense/Bind/forms/dnsbl.xml"
        ).read_text()
        named_template = (
            self.bind_root
            / "src/opnsense/service/templates/OPNsense/Bind/named"
        ).read_text()

        self.assertIn('<version>1.0.7</version>', model)
        self.assertIn('<memoryguard type="IntegerField">', model)
        memory_guard = model.split("<memoryguard", 1)[1].split("</memoryguard>", 1)[0]
        self.assertIn('<Default>300</Default>', memory_guard)
        self.assertIn('<MinimumValue>0</MinimumValue>', memory_guard)
        self.assertIn('<MaximumValue>32768</MaximumValue>', memory_guard)
        self.assertIn('<id>dnsbl.memoryguard</id>', form)
        self.assertIn('<label>Memory Guard (MB)</label>', form)
        self.assertIn(
            "Reserved free RAM while BIND loads DNSBL/RPZ. Reaching the floor disables DNSBL and restarts BIND. Set to 0 to disable memory guard.",
            form,
        )
        self.assertIn("named_memory_guard_mb=", named_template)

    def test_start_path_runs_the_memory_guard_before_starting_watcher(self):
        start = (
            self.bind_root
            / "src/opnsense/scripts/OPNsense/Bind/bindStart.sh"
        ).read_text()

        guard = '"${BIND_START_GUARD}" "${named_pid}" &'
        self.assertIn('named_pid=$("${BIND_START_PGREP}" -o named) || exit 1', start)
        self.assertIn(guard, start)
        self.assertLess(
            start.index(guard),
            start.index('"${BIND_START_DHCPWATCHER}"'),
        )

    def test_safe_restart_preserves_the_memory_guard_recovery_status(self):
        start = (
            self.bind_root
            / "src/opnsense/scripts/OPNsense/Bind/bindStart.sh"
        ).read_text()

        self.assertIn('dnsbl_enabled()', start)
        self.assertIn('if dnsbl_enabled; then', start)
        self.assertIn('previous_stage=$("${BIND_START_STATUS}" --stage)', start)
        self.assertIn('[ "${previous_stage}" != "guard_recovered" ]', start)

    def test_guard_recovery_disables_dnsbl_reloads_templates_and_starts_named(self):
        self.assertTrue(self.recovery.is_file())
        self.assertTrue(self.disable_script.is_file())

        disable_source = self.disable_script.read_text()
        self.assertIn('require_once("util.inc")', disable_source)
        self.assertIn('require_once("config.inc")', disable_source)
        self.assertIn("['OPNsense']['bind']['dnsbl']['enabled'] = '0'", disable_source)
        self.assertIn("write_config(", disable_source)

        recovery_source = self.recovery.read_text()
        self.assertIn("template reload OPNsense/Bind", recovery_source)
        self.assertIn('"${NAMED_RECOVERY_NAMED_RC}" start', recovery_source)

    def test_recovery_reloads_without_dnsbl_then_starts_named(self):
        with tempfile.TemporaryDirectory(dir=self.bind_root) as temp_dir:
            temp_path = pathlib.Path(temp_dir)
            mock_bin = temp_path / "bin"
            mock_bin.mkdir()
            event_file = temp_path / "events.log"

            for name in ("disable", "configctl", "named", "logger"):
                self._mock(
                    mock_bin,
                    name,
                    f"#!/bin/sh\nprintf '{name} %s\\n' \"$*\" >> \"$TEST_EVENTS\"\n",
                )

            result = subprocess.run(
                [self.recovery],
                env=os.environ | {
                    "NAMED_RECOVERY_DISABLE": str(mock_bin / "disable"),
                    "NAMED_RECOVERY_CONFIGCTL": str(mock_bin / "configctl"),
                    "NAMED_RECOVERY_NAMED_RC": str(mock_bin / "named"),
                    "NAMED_RECOVERY_LOGGER": str(mock_bin / "logger"),
                    "TEST_EVENTS": str(event_file),
                },
                text=True,
                capture_output=True,
                check=False,
            )

            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(
                event_file.read_text().splitlines()[:3],
                [
                    "disable ",
                    "configctl template reload OPNsense/Bind",
                    "named start",
                ],
            )

    def test_guard_stops_named_and_logs_when_free_memory_is_below_floor(self):
        self.assertTrue(self.guard.is_file())

        with tempfile.TemporaryDirectory(dir=self.bind_root) as temp_dir:
            temp_path = pathlib.Path(temp_dir)
            mock_bin = temp_path / "bin"
            mock_bin.mkdir()
            dnsbl_file = temp_path / "dnsbl.inc"
            dnsbl_file.write_text("example.test CNAME .\n")
            log_file = temp_path / "logger.log"
            kill_file = temp_path / "kill.log"
            recovery_file = temp_path / "recovery.log"

            self._mock(mock_bin, "getconf", "#!/bin/sh\necho 4096\n")
            self._mock(mock_bin, "sysctl", "#!/bin/sh\necho 1\n")
            self._mock(mock_bin, "pgrep", "#!/bin/sh\necho 4242\n")
            self._mock(mock_bin, "ps", "#!/bin/sh\necho 2048\n")
            self._mock(mock_bin, "sleep", "#!/bin/sh\nexit 0\n")
            self._mock(
                mock_bin,
                "logger",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_LOG\"\n",
            )
            self._mock(
                mock_bin,
                "kill",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_KILL_LOG\"\n",
            )
            self._mock(
                mock_bin,
                "recover",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_RECOVERY_LOG\"\n",
            )

            environment = os.environ | {
                "PATH": f"{mock_bin}:/bin:/usr/bin",
                "NAMED_GUARD_DNSBL_FILE": str(dnsbl_file),
                "NAMED_GUARD_ENABLED": "1",
                "NAMED_GUARD_MIN_FREE_KB": "512000",
                "NAMED_GUARD_TIMEOUT_SECONDS": "1",
                "NAMED_GUARD_SAMPLE_SECONDS": "0.1",
                "NAMED_GUARD_GETCONF": str(mock_bin / "getconf"),
                "NAMED_GUARD_LOGGER": str(mock_bin / "logger"),
                "NAMED_GUARD_PGREP": str(mock_bin / "pgrep"),
                "NAMED_GUARD_PS": str(mock_bin / "ps"),
                "NAMED_GUARD_SLEEP": str(mock_bin / "sleep"),
                "NAMED_GUARD_SYSCTL": str(mock_bin / "sysctl"),
                "NAMED_GUARD_KILL": str(mock_bin / "kill"),
                "NAMED_GUARD_RECOVER": str(mock_bin / "recover"),
                "TEST_LOG": str(log_file),
                "TEST_KILL_LOG": str(kill_file),
                "TEST_RECOVERY_LOG": str(recovery_file),
            }
            result = subprocess.run(
                [self.guard],
                env=environment,
                text=True,
                capture_output=True,
                check=False,
            )

            self.assertNotEqual(
                result.returncode,
                0,
                (
                    f"stdout={result.stdout!r} stderr={result.stderr!r} "
                    f"logger={log_file.read_text() if log_file.exists() else ''!r} "
                    f"kill={kill_file.read_text() if kill_file.exists() else ''!r}"
                ),
            )
            self.assertIn("-TERM 4242", kill_file.read_text())
            self.assertIn("-KILL 4242", kill_file.read_text())
            self.assertIn("DNSBL startup memory guard stopped named", log_file.read_text())
            self.assertIn("4242", recovery_file.read_text())

    def test_guard_converts_the_configured_mib_floor_to_kib(self):
        with tempfile.TemporaryDirectory(dir=self.bind_root) as temp_dir:
            temp_path = pathlib.Path(temp_dir)
            mock_bin = temp_path / "bin"
            mock_bin.mkdir()
            dnsbl_file = temp_path / "dnsbl.inc"
            dnsbl_file.write_text("example.test CNAME .\n")
            rc_conf = temp_path / "named"
            rc_conf.write_text('named_dnsbl="ads"\nnamed_memory_guard_mb="300"\n')
            log_file = temp_path / "logger.log"
            kill_file = temp_path / "kill.log"

            self._mock(mock_bin, "getconf", "#!/bin/sh\necho 4096\n")
            self._mock(mock_bin, "sysctl", "#!/bin/sh\necho 1\n")
            self._mock(mock_bin, "pgrep", "#!/bin/sh\necho 4242\n")
            self._mock(mock_bin, "ps", "#!/bin/sh\necho 2048\n")
            self._mock(mock_bin, "sleep", "#!/bin/sh\nexit 0\n")
            self._mock(
                mock_bin,
                "logger",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_LOG\"\n",
            )
            self._mock(
                mock_bin,
                "kill",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_KILL_LOG\"\n",
            )
            self._mock(mock_bin, "recover", "#!/bin/sh\nexit 0\n")

            result = subprocess.run(
                [self.guard],
                env=os.environ | {
                    "PATH": f"{mock_bin}:/bin:/usr/bin",
                    "NAMED_GUARD_DNSBL_FILE": str(dnsbl_file),
                    "NAMED_GUARD_RC_CONF": str(rc_conf),
                    "NAMED_GUARD_TIMEOUT_SECONDS": "1",
                    "NAMED_GUARD_SAMPLE_SECONDS": "0.1",
                    "NAMED_GUARD_GETCONF": str(mock_bin / "getconf"),
                    "NAMED_GUARD_LOGGER": str(mock_bin / "logger"),
                    "NAMED_GUARD_PGREP": str(mock_bin / "pgrep"),
                    "NAMED_GUARD_PS": str(mock_bin / "ps"),
                    "NAMED_GUARD_SLEEP": str(mock_bin / "sleep"),
                    "NAMED_GUARD_SYSCTL": str(mock_bin / "sysctl"),
                    "NAMED_GUARD_KILL": str(mock_bin / "kill"),
                    "NAMED_GUARD_RECOVER": str(mock_bin / "recover"),
                    "TEST_LOG": str(log_file),
                    "TEST_KILL_LOG": str(kill_file),
                },
                text=True,
                capture_output=True,
                check=False,
            )

            self.assertNotEqual(result.returncode, 0)
            self.assertIn("below the 307200 KiB minimum", log_file.read_text())

    def test_zero_memory_guard_exits_without_stopping_named(self):
        with tempfile.TemporaryDirectory(dir=self.bind_root) as temp_dir:
            temp_path = pathlib.Path(temp_dir)
            mock_bin = temp_path / "bin"
            mock_bin.mkdir()
            dnsbl_file = temp_path / "dnsbl.inc"
            dnsbl_file.write_text("example.test CNAME .\n")
            rc_conf = temp_path / "named"
            rc_conf.write_text('named_dnsbl="ads"\nnamed_memory_guard_mb="0"\n')
            log_file = temp_path / "logger.log"
            kill_file = temp_path / "kill.log"

            self._mock(mock_bin, "getconf", "#!/bin/sh\necho 4096\n")
            self._mock(mock_bin, "sysctl", "#!/bin/sh\necho 1\n")
            self._mock(mock_bin, "pgrep", "#!/bin/sh\necho 4242\n")
            self._mock(mock_bin, "ps", "#!/bin/sh\necho 2048\n")
            self._mock(mock_bin, "sleep", "#!/bin/sh\nexit 0\n")
            self._mock(
                mock_bin,
                "logger",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_LOG\"\n",
            )
            self._mock(
                mock_bin,
                "kill",
                "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$TEST_KILL_LOG\"\n",
            )

            result = subprocess.run(
                [self.guard],
                env=os.environ | {
                    "PATH": f"{mock_bin}:/bin:/usr/bin",
                    "NAMED_GUARD_DNSBL_FILE": str(dnsbl_file),
                    "NAMED_GUARD_RC_CONF": str(rc_conf),
                    "NAMED_GUARD_TIMEOUT_SECONDS": "1",
                    "NAMED_GUARD_SAMPLE_SECONDS": "0.1",
                    "NAMED_GUARD_GETCONF": str(mock_bin / "getconf"),
                    "NAMED_GUARD_LOGGER": str(mock_bin / "logger"),
                    "NAMED_GUARD_PGREP": str(mock_bin / "pgrep"),
                    "NAMED_GUARD_PS": str(mock_bin / "ps"),
                    "NAMED_GUARD_SLEEP": str(mock_bin / "sleep"),
                    "NAMED_GUARD_SYSCTL": str(mock_bin / "sysctl"),
                    "NAMED_GUARD_KILL": str(mock_bin / "kill"),
                    "TEST_LOG": str(log_file),
                    "TEST_KILL_LOG": str(kill_file),
                },
                text=True,
                capture_output=True,
                check=False,
            )

            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertFalse(kill_file.exists())
            self.assertFalse(log_file.exists())

    @staticmethod
    def _mock(directory, name, content):
        path = directory / name
        path.write_text(content)
        path.chmod(0o755)


if __name__ == "__main__":
    unittest.main()
