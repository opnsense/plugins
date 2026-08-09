# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.

import os
import pathlib
import subprocess
import tempfile

import pytest


BIND_ROOT = pathlib.Path(__file__).resolve().parents[1]
PACKAGE_HELPER = (
    BIND_ROOT / "src/opnsense/scripts/OPNsense/Bind/bindPackageZones.py"
)
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
    assert PACKAGE_HELPER.is_file(), "release package zone helper is missing"
else:
    pytestmark = pytest.mark.skip(reason="release version predates package lifecycle hooks")


@pytest.fixture
def executable_tmp_path():
    with tempfile.TemporaryDirectory(dir=BIND_ROOT) as directory:
        yield pathlib.Path(directory)


def _write_executable(path, source):
    path.write_text(source)
    path.chmod(0o755)


def _fixture(tmp_path, *, freeze_failure=""):
    zone_dir = tmp_path / "primary"
    zone_dir.mkdir()
    config = tmp_path / "config.xml"
    config.write_text(
        """<opnsense><OPNsense><bind><domain><domains>
        <domain uuid="dynamic"><domainname>dynamic.example</domainname><type>primary</type><allowrndcupdate>1</allowrndcupdate></domain>
        <domain uuid="reverse"><domainname>1.168.192.in-addr.arpa</domainname><type>reverse</type><allowrndcupdate>1</allowrndcupdate></domain>
        <domain uuid="watcher"><domainname>watcher.example</domainname><type>primary</type><allowrndcupdate>0</allowrndcupdate></domain>
        <domain uuid="static"><domainname>static.example</domainname><type>primary</type><allowrndcupdate>0</allowrndcupdate></domain>
        </domains></domain><tsig><keys><key uuid="watcher-key"><enabled>1</enabled></key></keys></tsig>
        <watcher><mappings><mapping><enabled>1</enabled><hostname_suffix>watcher</hostname_suffix><reverse_zone>reverse</reverse_zone><tsigkey>watcher-key</tsigkey></mapping></mappings></watcher>
        </bind></OPNsense></opnsense>"""
    )
    for zone in (
        "dynamic.example", "1.168.192.in-addr.arpa", "watcher.example",
        "static.example",
    ):
        (zone_dir / f"{zone}.db").write_text(f"static {zone}\n")
        (zone_dir / f"{zone}.db.jnl").write_text("journal\n")
    events = tmp_path / "events"
    events.write_text("")
    rndc = tmp_path / "rndc"
    _write_executable(
        rndc,
        """#!/bin/sh
printf 'rndc %s %s\n' "$1" "$2" >> "$TEST_EVENTS"
if [ "$1" = freeze ] && [ "$2" = dynamic.example ]; then
    printf '%s\n' 'external non-watcher record' >> "$TEST_ZONE_DIR/dynamic.example.db"
    rm -f "$TEST_ZONE_DIR/dynamic.example.db.jnl"
fi
if [ "$1" = freeze ] && [ "$2" = watcher.example ]; then
    printf '%s\n' 'watcher record' >> "$TEST_ZONE_DIR/watcher.example.db"
fi
if [ "$1" = freeze ] && [ "$2" = "$TEST_FREEZE_FAILURE" ]; then exit 44; fi
exit 0
""",
    )
    named = tmp_path / "named"
    _write_executable(
        named,
        """#!/bin/sh
printf 'named %s\n' "$1" >> "$TEST_EVENTS"
exit 0
""",
    )
    environment = os.environ | {
        "BIND_STOP_CONFIG": str(config),
        "BIND_STOP_NAMED_RC": str(named),
        "BIND_STOP_RNDC": str(rndc),
        "BIND_STOP_WATCHER_CONFIG": str(tmp_path / "watcher.conf"),
        "BIND_STOP_ZONE_DIR": str(zone_dir),
        "BIND_STOP_STATE_FILE": str(tmp_path / "state.json"),
        "BIND_STOP_WATCHER_PIDFILE": str(tmp_path / "watcher.pid"),
        "TEST_EVENTS": str(events),
        "TEST_ZONE_DIR": str(zone_dir),
        "TEST_FREEZE_FAILURE": freeze_failure,
    }
    return zone_dir, events, environment


def test_package_backup_preserves_non_watcher_dynamic_record(executable_tmp_path):
    zone_dir, events, environment = _fixture(executable_tmp_path)
    backup = executable_tmp_path / "backup"
    backup.mkdir(mode=0o700)

    prepare = subprocess.run(
        ["python3", str(PACKAGE_HELPER), "prepare", str(backup)],
        env=environment,
        text=True,
        capture_output=True,
        check=False,
    )
    assert prepare.returncode == 0, prepare.stderr
    assert "external non-watcher record" in (zone_dir / "dynamic.example.db").read_text()
    assert not (zone_dir / "dynamic.example.db.jnl").exists()
    assert not (zone_dir / "1.168.192.in-addr.arpa.db.jnl").exists()
    assert not (zone_dir / "watcher.example.db.jnl").exists()
    assert (zone_dir / "static.example.db.jnl").exists()

    (zone_dir / "dynamic.example.db").write_text("regenerated static model\n")
    (zone_dir / "watcher.example.db").write_text("regenerated static model\n")
    restore = subprocess.run(
        ["python3", str(PACKAGE_HELPER), "restore", str(backup)],
        env=environment,
        text=True,
        capture_output=True,
        check=False,
    )
    assert restore.returncode == 0, restore.stderr
    assert "external non-watcher record" in (zone_dir / "dynamic.example.db").read_text()
    assert "watcher record" in (zone_dir / "watcher.example.db").read_text()
    discard = subprocess.run(
        ["python3", str(PACKAGE_HELPER), "discard", str(backup)],
        env=environment,
        text=True,
        capture_output=True,
        check=False,
    )
    assert discard.returncode == 0, discard.stderr
    assert not backup.exists()
    assert events.read_text().splitlines() == [
        "rndc freeze 1.168.192.in-addr.arpa",
        "rndc freeze dynamic.example",
        "rndc freeze watcher.example",
        "named stop",
    ]


def test_freeze_failure_thaws_prior_zones_without_stopping_named(executable_tmp_path):
    _zone_dir, events, environment = _fixture(
        executable_tmp_path, freeze_failure="dynamic.example"
    )
    backup = executable_tmp_path / "backup"
    backup.mkdir(mode=0o700)

    result = subprocess.run(
        ["python3", str(PACKAGE_HELPER), "prepare", str(backup)],
        env=environment,
        text=True,
        capture_output=True,
        check=False,
    )

    assert result.returncode == 1
    assert events.read_text().splitlines() == [
        "rndc freeze 1.168.192.in-addr.arpa",
        "rndc freeze dynamic.example",
        "rndc thaw 1.168.192.in-addr.arpa",
    ]


def test_invalid_configuration_fails_before_rndc_or_service_mutation(executable_tmp_path):
    _zone_dir, events, environment = _fixture(executable_tmp_path)
    pathlib.Path(environment["BIND_STOP_CONFIG"]).write_text("<opnsense>")
    backup = executable_tmp_path / "backup"
    backup.mkdir(mode=0o700)

    result = subprocess.run(
        ["python3", str(PACKAGE_HELPER), "prepare", str(backup)],
        env=environment,
        text=True,
        capture_output=True,
        check=False,
    )

    assert result.returncode == 1
    assert events.read_text() == ""
