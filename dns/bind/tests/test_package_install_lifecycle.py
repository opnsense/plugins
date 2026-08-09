# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.

import os
import pathlib
import subprocess
import tempfile

import pytest


BIND_ROOT = pathlib.Path(__file__).resolve().parents[1]
PRE_INSTALL = BIND_ROOT / "+POST_INSTALL.pre"
POST_INSTALL = BIND_ROOT / "+POST_INSTALL.post"
STOP_SCRIPT = BIND_ROOT / "src/opnsense/scripts/OPNsense/Bind/bindStop.py"
MAKEFILE = BIND_ROOT / "Makefile"

if MAKEFILE.is_file():
    version_line = next(
        line for line in MAKEFILE.read_text().splitlines()
        if line.startswith("PLUGIN_VERSION=")
    )
    revision_line = next(
        line for line in MAKEFILE.read_text().splitlines()
        if line.startswith("PLUGIN_REVISION=")
    )
    PLUGIN_VERSION = tuple(
        int(part) for part in version_line.split("=", 1)[1].strip().split(".")
    )
    PLUGIN_REVISION = int(revision_line.split("=", 1)[1].strip())
else:
    PLUGIN_VERSION = (0,)
    PLUGIN_REVISION = 0

if (PLUGIN_VERSION, PLUGIN_REVISION) >= ((1, 36), 11):
    missing = [
        str(path.relative_to(BIND_ROOT))
        for path in (PRE_INSTALL, POST_INSTALL, STOP_SCRIPT)
        if not path.is_file()
    ]
    assert not missing, f"release lifecycle implementation is missing: {missing}"
else:
    pytestmark = pytest.mark.skip(reason="release version predates package lifecycle hooks")


@pytest.fixture
def executable_tmp_path():
    with tempfile.TemporaryDirectory(dir=BIND_ROOT) as directory:
        yield pathlib.Path(directory)


def _write_executable(path, source):
    path.write_text(source)
    path.chmod(0o755)


def _run_hook(tmp_path, *, initial="running", initial_status_error=0,
              later_status_error=0, stop_error=0, framework_error=0,
              start_error=0, restore_error=0, partial_start=False):
    state = tmp_path / "state"
    state.write_text(initial)
    events = tmp_path / "events"
    events.write_text("")
    status_count = tmp_path / "status-count"
    status_count.write_text("0")

    named_rc = tmp_path / "named"
    _write_executable(
        named_rc,
        """#!/bin/sh
printf '%s\n' named-status >> "$TEST_EVENTS"
count=$(( $(cat "$TEST_STATUS_COUNT") + 1 ))
printf '%s\n' "$count" > "$TEST_STATUS_COUNT"
if [ "$count" -eq 1 ]; then error=$TEST_INITIAL_STATUS_ERROR; else error=$TEST_LATER_STATUS_ERROR; fi
if [ "$error" -ne 0 ]; then exit "$error"; fi
[ "$(cat "$TEST_STATE")" = running ]
""",
    )
    stop = tmp_path / "zone-helper"
    _write_executable(
        stop,
        """#!/bin/sh
case "$1" in
    prepare)
        printf '%s\n' bind-stop >> "$TEST_EVENTS"
        if [ "$TEST_STOP_ERROR" -ne 0 ]; then exit "$TEST_STOP_ERROR"; fi
        printf '%s\n' stopped > "$TEST_STATE"
        ;;
    restore)
        printf '%s\n' zone-restore >> "$TEST_EVENTS"
        if [ "$TEST_RESTORE_ERROR" -ne 0 ]; then exit "$TEST_RESTORE_ERROR"; fi
        ;;
    discard)
        printf '%s\n' zone-discard >> "$TEST_EVENTS"
        rmdir "$2"
        ;;
esac
""",
    )
    start = tmp_path / "start"
    _write_executable(
        start,
        """#!/bin/sh
printf '%s\n' bind-start >> "$TEST_EVENTS"
if [ "$TEST_PARTIAL_START" = yes ]; then printf '%s\n' running > "$TEST_STATE"; fi
if [ "$TEST_START_ERROR" -ne 0 ]; then exit "$TEST_START_ERROR"; fi
printf '%s\n' running > "$TEST_STATE"
""",
    )
    framework = tmp_path / "framework"
    _write_executable(
        framework,
        """#!/bin/sh
printf '%s\n' framework >> "$TEST_EVENTS"
exit "$TEST_FRAMEWORK_ERROR"
""",
    )

    script = tmp_path / "post-install"
    script.write_text(
        PRE_INSTALL.read_text()
        + '\n"$TEST_FRAMEWORK"\n'
        + POST_INSTALL.read_text()
    )
    script.chmod(0o755)
    result = subprocess.run(
        ["/bin/sh", str(script)],
        env=os.environ | {
            "BIND_PACKAGE_NAMED_RC": str(named_rc),
            "BIND_PACKAGE_ZONE_HELPER": str(stop),
            "BIND_PACKAGE_SNAPSHOT_PARENT": str(tmp_path),
            "BIND_PACKAGE_START": str(start),
            "TEST_EVENTS": str(events),
            "TEST_STATE": str(state),
            "TEST_STATUS_COUNT": str(status_count),
            "TEST_INITIAL_STATUS_ERROR": str(initial_status_error),
            "TEST_LATER_STATUS_ERROR": str(later_status_error),
            "TEST_STOP_ERROR": str(stop_error),
            "TEST_FRAMEWORK": str(framework),
            "TEST_FRAMEWORK_ERROR": str(framework_error),
            "TEST_START_ERROR": str(start_error),
            "TEST_RESTORE_ERROR": str(restore_error),
            "TEST_PARTIAL_START": "yes" if partial_start else "no",
        },
        text=True,
        capture_output=True,
        check=False,
    )
    return result, events.read_text().splitlines(), state.read_text().strip()


def test_running_service_is_stopped_before_render_and_restarted_after(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path)

    assert result.returncode == 0, result.stderr
    assert events == [
        "named-status", "bind-stop", "framework", "zone-restore", "named-status",
        "bind-start", "named-status", "zone-discard",
    ]
    assert state == "running"


def test_stopped_service_is_not_started(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, initial="stopped")

    assert result.returncode == 0, result.stderr
    assert events == ["named-status", "framework"]
    assert state == "stopped"


def test_framework_failure_restores_running_service_and_stays_failed(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, framework_error=41)

    assert result.returncode == 41
    assert events == [
        "named-status", "bind-stop", "framework", "zone-restore", "named-status",
        "bind-start", "named-status", "zone-discard",
    ]
    assert state == "running"


def test_stop_failure_prevents_rendering(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, stop_error=42)

    assert result.returncode == 42
    assert events == ["named-status", "bind-stop", "named-status"]
    assert state == "running"


def test_initial_status_error_prevents_mutation(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, initial_status_error=70)

    assert result.returncode == 70
    assert events == ["named-status"]
    assert state == "running"


def test_restart_failure_makes_install_fail(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, start_error=43)

    assert result.returncode == 43
    assert events == [
        "named-status", "bind-stop", "framework", "zone-restore", "named-status",
        "bind-start", "named-status", "named-status", "bind-start", "named-status",
    ]
    assert state == "stopped"


def test_later_status_error_fails_without_blind_start(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, later_status_error=70)

    assert result.returncode == 70
    assert events == [
        "named-status", "bind-stop", "framework", "zone-restore", "named-status",
        "named-status",
    ]
    assert state == "stopped"


def test_partial_start_failure_reports_failure_but_keeps_running_service(executable_tmp_path):
    result, events, state = _run_hook(
        executable_tmp_path, start_error=43, partial_start=True
    )

    assert result.returncode == 43
    assert events == [
        "named-status", "bind-stop", "framework", "zone-restore", "named-status",
        "bind-start", "named-status", "zone-discard",
    ]
    assert state == "running"


def test_zone_restore_failure_fails_without_restarting(executable_tmp_path):
    result, events, state = _run_hook(executable_tmp_path, restore_error=45)

    assert result.returncode == 45
    assert events == [
        "named-status", "bind-stop", "framework", "zone-restore", "zone-restore",
    ]
    assert state == "stopped"


def test_normal_stop_keeps_primary_journals_and_clears_reverse_scope(executable_tmp_path):
    zone_dir = executable_tmp_path / "primary"
    zone_dir.mkdir()
    config = executable_tmp_path / "config.xml"
    config.write_text(
        """<opnsense><bind><domain><domains>
        <domain uuid="dynamic"><domainname>dynamic.example</domainname><type>primary</type><allowrndcupdate>1</allowrndcupdate></domain>
        <domain uuid="default"><domainname>default.example</domainname></domain>
        <domain uuid="static"><domainname>static.example</domainname><type>primary</type><allowrndcupdate>0</allowrndcupdate></domain>
        <domain uuid="reverse"><domainname>1.168.192.in-addr.arpa</domainname><type>reverse</type><allowrndcupdate>1</allowrndcupdate></domain>
        <domain uuid="secondary"><domainname>secondary.example</domainname><type>secondary</type><allowrndcupdate>1</allowrndcupdate></domain>
        <domain uuid="disabled"><enabled>0</enabled><domainname>disabled.example</domainname><type>primary</type><allowrndcupdate>1</allowrndcupdate></domain>
        </domains></domain></bind></opnsense>"""
    )
    zones = {
        "dynamic.example": True,
        "default.example": True,
        "static.example": True,
        "1.168.192.in-addr.arpa": False,
        "secondary.example": True,
        "disabled.example": True,
    }
    for zone in zones:
        (zone_dir / f"{zone}.db.jnl").write_text("")
    named = executable_tmp_path / "named-stop"
    _write_executable(named, "#!/bin/sh\nexit 0\n")
    watcher = executable_tmp_path / "watcher.conf"
    watcher.write_text("")

    result = subprocess.run(
        ["python3", str(STOP_SCRIPT)],
        env=os.environ | {
            "BIND_STOP_CONFIG": str(config),
            "BIND_STOP_NAMED_RC": str(named),
            "BIND_STOP_WATCHER_CONFIG": str(watcher),
            "BIND_STOP_ZONE_DIR": str(zone_dir),
            "BIND_STOP_STATE_FILE": str(executable_tmp_path / "state.json"),
            "BIND_STOP_WATCHER_PIDFILE": str(executable_tmp_path / "watcher.pid"),
        },
        text=True,
        capture_output=True,
        check=False,
    )

    assert result.returncode == 0, result.stderr
    for zone, should_exist in zones.items():
        assert (zone_dir / f"{zone}.db.jnl").exists() is should_exist
