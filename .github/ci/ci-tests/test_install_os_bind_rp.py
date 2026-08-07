"""Regression coverage for the interactive os-bind-rp installer."""

from __future__ import annotations

import os
import stat
import subprocess
import tempfile
from pathlib import Path


REPOSITORY_ROOT = Path(__file__).resolve().parents[3]
INSTALLER = REPOSITORY_ROOT / "scripts" / "install-os-bind-rp.sh"
PUBLIC_KEY_SHA256 = "bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e"
FIXTURE_ROOT = REPOSITORY_ROOT / ".github" / "ci-local"


def write_executable(path: Path, contents: str) -> None:
    path.write_text(contents, encoding="utf-8")
    path.chmod(0o755)


def installer_environment(
    tmp_path: Path,
    *,
    opnsense_version: str = "OPNsense 26.1.11_10 (amd64)",
    bind920: str = "bind920|9.20.26_2|dns/bind920",
    bind_tools: str = "bind-tools|9.20.26_2|dns/bind-tools",
    os_bind: str = "",
    os_bind_rp: str = "",
    confirmation: str | None = None,
    key_sha256: str = PUBLIC_KEY_SHA256,
    fetch_failure: bool = False,
    archive_checksum: str = "2$" + "a" * 64,
    install_failure: bool = False,
    plugin_install_failure: bool = False,
    fetch_layout: str = "all",
    wrong_owner: bool = False,
    pkg_locked: bool = False,
    mutate_frozen_archive: bool = False,
) -> tuple[dict[str, str], Path, Path]:
    log = tmp_path / "commands.log"
    tty = tmp_path / "tty"
    if confirmation is not None:
        tty.write_text(f"{confirmation}\n", encoding="utf-8")
    config = tmp_path / "config.xml"
    config.write_text("<opnsense><bind/></opnsense>\n", encoding="utf-8")
    config.chmod(0o640)
    opnsense_repository = tmp_path / "OPNsense.conf"
    opnsense_repository.write_text("OPNsense: { enabled: yes }\n", encoding="utf-8")
    lock_marker = tmp_path / "pkg-locked"
    if pkg_locked:
        lock_marker.touch()

    environment = os.environ.copy()
    environment.update(
        {
            "RP_PKG_REPOSITORY_DIR": str(tmp_path / "repos"),
            "RP_PKG_KEYS_DIR": str(tmp_path / "keys"),
            "RP_TEMPORARY_DIRECTORY": str(tmp_path / "temporary"),
            "RP_TTY_PATH": str(tty),
            "RP_TEST_BIND920": bind920,
            "RP_TEST_BIND_TOOLS": bind_tools,
            "RP_TEST_OS_BIND": os_bind,
            "RP_TEST_OS_BIND_RP": os_bind_rp,
            "RP_TEST_FETCH_FAILURE": "yes" if fetch_failure else "no",
            "RP_TEST_INSTALL_FAILURE": "yes" if install_failure else "no",
            "RP_TEST_PLUGIN_INSTALL_FAILURE": "yes" if plugin_install_failure else "no",
            "RP_TEST_FETCH_LAYOUT": fetch_layout,
            "RP_TEST_WRONG_OWNER": "yes" if wrong_owner else "no",
            "RP_TEST_MUTATE_FROZEN_ARCHIVE": "yes" if mutate_frozen_archive else "no",
            "RP_TEST_ARCHIVE_CHECKSUM": archive_checksum,
            "RP_TEST_KEY_SHA256": key_sha256,
            "RP_TEST_LOG": str(log),
            "RP_TEST_OPNSENSE_VERSION": opnsense_version,
            "RP_TEST_FALLBACK_MARKER": str(tmp_path / "fallback-installed"),
            "RP_TEST_PLUGIN_MARKER": str(tmp_path / "plugin-installed"),
            "RP_TEST_OFFICIAL_REMOVED_MARKER": str(tmp_path / "official-removed"),
            "RP_TEST_LOCK_MARKER": str(lock_marker),
            "RP_CONFIG_FILE": str(config),
            "RP_BACKUP_ROOT": str(tmp_path / "backups"),
            "RP_OPNSENSE_REPOSITORY_CONFIG": str(opnsense_repository),
        }
    )
    return environment, log, tmp_path / "repos"


def write_command_fixtures(directory: Path) -> None:
    write_executable(
        directory / "opnsense-version",
        "#!/bin/sh\nprintf '%s\\n' \"$RP_TEST_OPNSENSE_VERSION\"\n",
    )
    write_executable(
        directory / "fetch",
        "#!/bin/sh\n"
        "{ printf 'fetch'; for argument in \"$@\"; do printf ' %s' \"$argument\"; done; printf '\\n'; } >> \"$RP_TEST_LOG\"\n"
        "[ \"${RP_TEST_FETCH_FAILURE:-no}\" = yes ] && exit 1\n"
        "[ \"$1\" = -o ] || exit 64\n"
        "printf 'test public key\\n' > \"$2\"\n",
    )
    write_executable(
        directory / "sha256",
        "#!/bin/sh\n"
        "case \"$2\" in\n"
        "  */resolver-plugins.pub) printf '%s\\n' \"$RP_TEST_KEY_SHA256\";;\n"
        "  *) sha256sum \"$2\" | awk '{ print $1 }';;\n"
        "esac\n",
    )
    write_executable(
        directory / "pkg",
        r'''#!/usr/bin/env python3
import hashlib
import os
from pathlib import Path
import re
import sys


raw = sys.argv[1:]
with open(os.environ["RP_TEST_LOG"], "a", encoding="utf-8") as stream:
    stream.write("pkg " + " ".join(raw) + "\n")

args = list(raw)
while args[:1] == ["-o"]:
    del args[:2]
if not args:
    raise SystemExit(64)
command, args = args[0], args[1:]

candidates = {
    "bind920": ("9.20.26_2", "dns/bind920"),
    "bind-tools": ("9.20.26_2", "dns/bind-tools"),
    "os-bind-rp": ("1.36_10", "opnsense/os-bind-rp"),
}


def marker(name):
    return Path(os.environ[name])


def version_key(value):
    return tuple(int(part) for part in re.findall(r"\d+", value))


def installed(name):
    if name == "bind920":
        if marker("RP_TEST_FALLBACK_MARKER").exists():
            return "bind920|9.20.26_2|dns/bind920"
        return os.environ.get("RP_TEST_BIND920", "")
    if name == "bind-tools":
        if marker("RP_TEST_FALLBACK_MARKER").exists():
            return "bind-tools|9.20.26_2|dns/bind-tools"
        return os.environ.get("RP_TEST_BIND_TOOLS", "")
    if name == "os-bind-rp":
        if marker("RP_TEST_PLUGIN_MARKER").exists():
            return "os-bind-rp|1.36_10|opnsense/os-bind-rp"
        return os.environ.get("RP_TEST_OS_BIND_RP", "")
    if name == "os-bind" and not marker("RP_TEST_OFFICIAL_REMOVED_MARKER").exists():
        return os.environ.get("RP_TEST_OS_BIND", "")
    if name == "pkg":
        return "pkg|2.3.1_1|ports-mgmt/pkg"
    return ""


if command == "version":
    left, right = args[-2:]
    comparison = (version_key(left) > version_key(right)) - (version_key(left) < version_key(right))
    print("<=>"[comparison + 1])
elif command == "update":
    pass
elif command == "rquery":
    expression = " ".join(args)
    for name, (version, origin) in candidates.items():
        if f"%n = {name}" in expression:
            print(f"{name}|{version}|{origin}")
elif command == "fetch":
    if os.environ.get("RP_TEST_FETCH_FAILURE") == "yes":
        raise SystemExit(1)
    destination = Path(args[args.index("-o") + 1])
    if os.environ.get("RP_TEST_FETCH_LAYOUT", "all") == "all":
        destination /= "All"
    identity = args[-1]
    destination.mkdir(parents=True, exist_ok=True)
    (destination / f"{identity}.pkg").write_bytes(f"archive:{identity}\n".encode())
elif command == "repo":
    repository = Path(args[-1])
    if (
        os.environ.get("RP_TEST_MUTATE_FROZEN_ARCHIVE") == "yes"
        and repository.name == "verified-repository"
    ):
        archive = next(repository.rglob("bind920-*.pkg"))
        archive.write_bytes(archive.read_bytes() + b"changed\n")
    (repository / "meta.conf").write_text("meta\n", encoding="utf-8")
    (repository / "packagesite.pkg").write_text("catalogue\n", encoding="utf-8")
elif command == "query" and "-F" in args:
    archive = Path(args[args.index("-F") + 1])
    identity = archive.name.removesuffix(".pkg")
    record = next(
        (
            f"{name}|{version}|{origin}"
            for name, (version, origin) in candidates.items()
            if identity == f"{name}-{version}"
        ),
        "",
    )
    if not record:
        record = next(
            (
                installed(name)
                for name in ("bind920", "bind-tools", "os-bind-rp", "os-bind")
                if installed(name) and identity == "-".join(installed(name).split("|")[:2])
            ),
            "",
        )
    name, version, origin = record.split("|")
    output_format = args[-1]
    if output_format == "%n|%v|%o":
        print(f"{name}|{version}|{origin}")
    elif output_format == "%Fp|%Fs":
        checksum = os.environ["RP_TEST_ARCHIVE_CHECKSUM"]
        print(f"/usr/local/{name}/one|{checksum}")
        print(f"/usr/local/{name}/two|{checksum}")
elif command == "query":
    expression = " ".join(args)
    for name in ("bind920", "bind-tools", "os-bind-rp", "os-bind", "pkg"):
        if f"%n = {name}" in expression:
            value = installed(name)
            if value:
                if args[-1] == "%Fp|%Fs":
                    checksum = os.environ["RP_TEST_ARCHIVE_CHECKSUM"]
                    print(f"/usr/local/{name}/one|{checksum}")
                    print(f"/usr/local/{name}/two|{checksum}")
                else:
                    print(value)
elif command == "info":
    print("pkg-2.3.1_1")
    for name in ("bind920", "bind-tools", "os-bind-rp", "os-bind"):
        value = installed(name)
        if value:
            package, version, _ = value.split("|")
            print(f"{package}-{version}")
elif command == "create":
    destination = Path(args[args.index("-o") + 1])
    destination.mkdir(parents=True, exist_ok=True)
    for name in args[args.index("-o") + 2:]:
        value = installed(name)
        if value:
            package, version, _ = value.split("|")
            (destination / f"{package}-{version}.pkg").write_bytes(
                f"recovery:{package}-{version}\n".encode()
            )
elif command == "install":
    if "-n" in args:
        print("The following package(s) will be affected:")
        for argument in args:
            if re.search(r"-[0-9]", argument):
                print(f"\t{argument}")
        raise SystemExit(1)
    else:
        if os.environ.get("RP_TEST_INSTALL_FAILURE") == "yes":
            raise SystemExit(1)
        identities = set(args)
        if "bind920-9.20.26_2" in identities and "bind-tools-9.20.26_2" in identities:
            marker("RP_TEST_FALLBACK_MARKER").touch()
        if "os-bind-rp-1.36_10" in identities:
            if os.environ.get("RP_TEST_PLUGIN_INSTALL_FAILURE") == "yes":
                raise SystemExit(1)
            marker("RP_TEST_PLUGIN_MARKER").touch()
            marker("RP_TEST_OFFICIAL_REMOVED_MARKER").touch()
elif command == "lock":
    if "-l" in args:
        if marker("RP_TEST_LOCK_MARKER").exists():
            print("pkg-2.3.1_1")
    elif "-u" in args:
        marker("RP_TEST_LOCK_MARKER").unlink(missing_ok=True)
    elif "-y" in args:
        marker("RP_TEST_LOCK_MARKER").touch()
elif command == "which":
    path = args[-1]
    name = path.split("/")[3]
    value = installed(name)
    if not value:
        raise SystemExit(1)
    package, version, _ = value.split("|")
    if os.environ.get("RP_TEST_WRONG_OWNER") == "yes":
        package = "wrong-owner"
        version = "1"
    print(f"{package}-{version}")
elif command == "check":
    pass
else:
    raise SystemExit(64)
''',
    )


def run_installer(tmp_path: Path, **kwargs: object) -> tuple[subprocess.CompletedProcess[str], Path, Path]:
    environment, log, repositories = installer_environment(tmp_path, **kwargs)
    FIXTURE_ROOT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(dir=FIXTURE_ROOT) as fixture_directory:
        fixtures = Path(fixture_directory)
        write_command_fixtures(fixtures)
        environment["PATH"] = f"{fixtures}:{environment['PATH']}"
        environment["RP_PKG_STATIC_COMMAND"] = str(fixtures / "pkg")
        result = subprocess.run(
            ["/bin/sh", INSTALLER], text=True, capture_output=True, check=False, env=environment
        )
    return result, log, repositories


def test_installs_current_plugin_for_the_detected_series_without_service_changes(tmp_path: Path) -> None:
    result, log, repositories = run_installer(tmp_path)

    assert result.returncode == 0, result.stderr
    assert "pkg-26.1" in (repositories / "resolver-plugins.conf").read_text(encoding="utf-8")
    calls = log.read_text(encoding="utf-8")
    assert "os-bind-rp-1.36_10" in calls
    assert "resolver-plugins-bind920" not in calls
    assert "configctl" not in calls
    assert "service" not in calls


def test_uses_eligible_opnsense_bind_without_prompting_or_fallback(tmp_path: Path) -> None:
    result, log, _ = run_installer(tmp_path)

    assert result.returncode == 0, result.stderr
    assert "Do you wish to update BIND?" not in result.stderr
    calls = log.read_text(encoding="utf-8")
    assert "bind920-9.20.26_2 bind-tools-9.20.26_2" not in calls


def test_rejects_26_1_before_the_required_core_floor(tmp_path: Path) -> None:
    result, log, _ = run_installer(tmp_path, opnsense_version="OPNsense 26.1.10 (amd64)")

    assert result.returncode != 0
    assert "OPNsense 26.1.11_10 or newer is required" in result.stderr
    calls = log.read_text(encoding="utf-8")
    assert "fetch " not in calls
    assert "pkg update" not in calls
    assert "pkg install" not in calls


def test_preserves_a_trusted_key_when_the_replacement_fails_verification(tmp_path: Path) -> None:
    key = tmp_path / "keys" / "resolver-plugins.pub"
    key.parent.mkdir()
    key.write_text("existing trusted key\n", encoding="utf-8")

    result, log, _ = run_installer(tmp_path, key_sha256="0" * 64)

    assert result.returncode != 0
    assert "public-key fingerprint verification failed" in result.stderr
    assert key.read_text(encoding="utf-8") == "existing trusted key\n"
    calls = log.read_text(encoding="utf-8")
    assert "pkg update" not in calls
    assert "pkg install" not in calls


def test_prompts_for_and_installs_the_fallback_when_bind_is_ineligible(tmp_path: Path) -> None:
    result, log, repositories = run_installer(
        tmp_path,
        bind920="bind920|9.20.25|dns/bind920",
        bind_tools="bind-tools|9.20.25|dns/bind-tools",
        confirmation="y",
    )

    assert result.returncode == 0, result.stderr
    assert "Installed bind920: bind920 9.20.25 from dns/bind920" in result.stderr
    assert "Installed bind-tools: bind-tools 9.20.25 from dns/bind-tools" in result.stderr
    assert "Available fallback: bind920 9.20.26_2 and bind-tools 9.20.26_2" in result.stderr
    assert "An update to BIND is required to address a breaking issue with DoT." in result.stderr
    assert (
        "Note: future OPNsense updates to BIND will still work as long as they are above the pinned version."
        in result.stderr
    )
    assert "Do you wish to update BIND? [y/N]" in result.stderr
    assert not (repositories / "resolver-plugins-bind920.conf").exists()
    calls = log.read_text(encoding="utf-8")
    live_installs = [line for line in calls.splitlines() if " install -y " in line]
    assert "bind920-9.20.26_2 bind-tools-9.20.26_2" in live_installs[0]
    assert "os-bind-rp-1.36_10" in live_installs[1]


def test_prompts_when_bind_tools_are_missing_or_from_the_wrong_origin(tmp_path: Path) -> None:
    result, _, _ = run_installer(
        tmp_path,
        bind_tools="bind-tools|9.20.26_1|resolver/bind-tools",
        confirmation="n",
    )

    assert result.returncode != 0
    assert "Installed bind920: bind920 9.20.26_2 from dns/bind920" in result.stderr
    assert "Installed bind-tools: bind-tools 9.20.26_1 from resolver/bind-tools" in result.stderr
    assert "BIND update declined; os-bind-rp was not installed." in result.stderr


def test_declining_bind_fallback_leaves_the_plugin_uninstalled(tmp_path: Path) -> None:
    result, log, _ = run_installer(
        tmp_path, bind920="bind920|9.20.25|dns/bind920", confirmation="n"
    )

    assert result.returncode != 0
    assert "BIND update declined; os-bind-rp was not installed." in result.stderr
    calls = log.read_text(encoding="utf-8")
    assert "bind920-9.20.26_2 bind-tools-9.20.26_2" not in calls
    assert "os-bind-rp-1.36_10" not in calls


def test_rejects_an_unsupported_opnsense_series(tmp_path: Path) -> None:
    result, _, _ = run_installer(tmp_path, opnsense_version="OPNsense 25.7.2 (amd64)")

    assert result.returncode != 0
    assert "unsupported OPNsense release series: 25.7" in result.stderr


def test_rejects_null_archive_checksums_before_any_package_install(tmp_path: Path) -> None:
    result, log, _ = run_installer(tmp_path, archive_checksum="(null)")

    assert result.returncode != 0
    assert "incompatible file checksum" in result.stderr
    assert " install " not in log.read_text(encoding="utf-8")
    assert not (tmp_path / "backups").exists()


def test_accepts_sha256_and_blake_checksum_prefixes_from_either_fetch_layout(
    tmp_path: Path,
) -> None:
    for index, (checksum, layout) in enumerate(
        (("1$" + "b" * 64, "direct"), ("2$" + "c" * 64, "all"))
    ):
        case_directory = tmp_path / str(index)
        case_directory.mkdir()
        result, _, _ = run_installer(
            case_directory, archive_checksum=checksum, fetch_layout=layout
        )
        assert result.returncode == 0, result.stderr


def test_rejects_a_frozen_archive_change_before_state_or_install(tmp_path: Path) -> None:
    result, log, _ = run_installer(tmp_path, mutate_frozen_archive=True)

    assert result.returncode != 0
    assert "verified archive changed" in result.stderr
    assert " install " not in log.read_text(encoding="utf-8")
    assert not (tmp_path / "backups").exists()


def test_official_plugin_replacement_uses_verified_exact_archives_and_keeps_backup(
    tmp_path: Path,
) -> None:
    result, log, _ = run_installer(
        tmp_path,
        os_bind="os-bind|1.34_3|opnsense/os-bind",
    )

    assert result.returncode == 0, result.stderr
    assert "Replacing official os-bind" in result.stderr
    backups = list((tmp_path / "backups").glob("os-bind-rp-install.*"))
    assert len(backups) == 1
    backup = backups[0]
    assert stat.S_IMODE(backup.stat().st_mode) == 0o700
    saved_config = backup / "config.xml.bak"
    assert saved_config.read_text(encoding="utf-8") == "<opnsense><bind/></opnsense>\n"
    assert stat.S_IMODE(saved_config.stat().st_mode) == 0o640

    calls = log.read_text(encoding="utf-8")
    assert calls.index(" fetch ") < calls.index(" repo ")
    assert calls.index(" install -n ") < calls.index(" install -y ")
    live_installs = [line for line in calls.splitlines() if " install -y " in line]
    assert live_installs
    assert all("REPOS_DIR=" in line and "isolated-repos" in line for line in live_installs)
    assert any("os-bind-rp-1.36_10" in line for line in live_installs)
    assert "-r resolver-plugins os-bind-rp" not in calls
    for package in ("bind-tools", "bind920", "os-bind-rp"):
        assert f"pkg query -e %n = {package} %Fp|%Fs" in calls


def test_reports_an_existing_resolver_plugin_upgrade(tmp_path: Path) -> None:
    result, _, _ = run_installer(
        tmp_path,
        os_bind_rp="os-bind-rp|1.36_9|opnsense/os-bind-rp",
    )

    assert result.returncode == 0, result.stderr
    assert "Upgrading installed os-bind-rp" in result.stderr


def test_rejects_archive_paths_owned_by_the_wrong_package(tmp_path: Path) -> None:
    result, _, _ = run_installer(tmp_path, wrong_owner=True)

    assert result.returncode != 0
    assert "installed file has the wrong owner" in result.stderr


def test_install_failure_retains_diagnostics_and_temporary_archives(tmp_path: Path) -> None:
    result, _, _ = run_installer(tmp_path, install_failure=True)

    assert result.returncode != 0
    backups = list((tmp_path / "backups").glob("os-bind-rp-install.*"))
    assert len(backups) == 1
    assert "Diagnostic state retained at" in result.stderr
    assert "Temporary package data retained at" in result.stderr
    temporary = tmp_path / "temporary"
    assert temporary.exists()
    assert list(temporary.rglob("*.pkg"))


def test_restores_the_original_pkg_lock_state_after_success_and_failure(tmp_path: Path) -> None:
    unlocked = tmp_path / "unlocked"
    unlocked.mkdir()
    result, _, _ = run_installer(unlocked)
    assert result.returncode == 0, result.stderr
    assert not (unlocked / "pkg-locked").exists()

    locked = tmp_path / "locked"
    locked.mkdir()
    result, _, _ = run_installer(locked, pkg_locked=True, install_failure=True)
    assert result.returncode != 0
    assert (locked / "pkg-locked").exists()


def test_partial_bind_update_failure_preserves_recovery_packages_and_instructions(
    tmp_path: Path,
) -> None:
    result, log, _ = run_installer(
        tmp_path,
        bind920="bind920|9.20.25|dns/bind920",
        bind_tools="bind-tools|9.20.25|dns/bind-tools",
        confirmation="y",
        os_bind="os-bind|1.34_3|opnsense/os-bind",
        plugin_install_failure=True,
    )

    assert result.returncode != 0
    backups = list((tmp_path / "backups").glob("os-bind-rp-install.*"))
    assert len(backups) == 1
    recovery = backups[0] / "recovery-packages"
    assert {path.name for path in recovery.glob("*.pkg") if path.name != "packagesite.pkg"} == {
        "bind-tools-9.20.25.pkg",
        "bind920-9.20.25.pkg",
        "os-bind-1.34_3.pkg",
    }
    assert "Recovery package repository:" in result.stderr
    assert "Dry-run recovery before applying it" in result.stderr
    live_installs = [
        line for line in log.read_text(encoding="utf-8").splitlines() if " install -y " in line
    ]
    assert "bind920-9.20.26_2 bind-tools-9.20.26_2" in live_installs[-2]
    assert "os-bind-rp-1.36_10" in live_installs[-1]


def test_declining_update_creates_no_durable_state(tmp_path: Path) -> None:
    result, _, _ = run_installer(
        tmp_path,
        bind920="bind920|9.20.25|dns/bind920",
        confirmation="n",
    )

    assert result.returncode != 0
    assert not (tmp_path / "backups").exists()
