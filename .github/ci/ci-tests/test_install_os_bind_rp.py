"""Regression coverage for the interactive os-bind-rp installer."""

from __future__ import annotations

import os
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
    bind920: str = "bind920|9.20.26_1|dns/bind920",
    bind_tools: str = "bind-tools|9.20.26_1|dns/bind-tools",
    confirmation: str | None = None,
    key_sha256: str = PUBLIC_KEY_SHA256,
    fetch_failure: bool = False,
) -> tuple[dict[str, str], Path, Path]:
    log = tmp_path / "commands.log"
    tty = tmp_path / "tty"
    if confirmation is not None:
        tty.write_text(f"{confirmation}\n", encoding="utf-8")

    environment = os.environ.copy()
    environment.update(
        {
            "RP_PKG_REPOSITORY_DIR": str(tmp_path / "repos"),
            "RP_PKG_KEYS_DIR": str(tmp_path / "keys"),
            "RP_TEMPORARY_DIRECTORY": str(tmp_path / "temporary"),
            "RP_TTY_PATH": str(tty),
            "RP_TEST_BIND920": bind920,
            "RP_TEST_BIND_TOOLS": bind_tools,
            "RP_TEST_FETCH_FAILURE": "yes" if fetch_failure else "no",
            "RP_TEST_KEY_SHA256": key_sha256,
            "RP_TEST_LOG": str(log),
            "RP_TEST_OPNSENSE_VERSION": opnsense_version,
            "RP_TEST_FALLBACK_MARKER": str(tmp_path / "fallback-installed"),
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
        "#!/bin/sh\nprintf '%s\\n' \"$RP_TEST_KEY_SHA256\"\n",
    )
    write_executable(
        directory / "pkg",
        "#!/bin/sh\n"
        "{ printf 'pkg'; for argument in \"$@\"; do printf ' %s' \"$argument\"; done; printf '\\n'; } >> \"$RP_TEST_LOG\"\n"
        "case \"$1\" in\n"
        "query)\n"
        "  case \"$*\" in\n"
        "    *'%n = bind920'*)\n"
        "      if [ -f \"$RP_TEST_FALLBACK_MARKER\" ]; then printf '%s\\n' 'bind920|9.20.26_1|dns/bind920'; else printf '%s\\n' \"${RP_TEST_BIND920:-}\"; fi;;\n"
        "    *'%n = bind-tools'*)\n"
        "      if [ -f \"$RP_TEST_FALLBACK_MARKER\" ]; then printf '%s\\n' 'bind-tools|9.20.26_1|dns/bind-tools'; else printf '%s\\n' \"${RP_TEST_BIND_TOOLS:-}\"; fi;;\n"
        "  esac;;\n"
        "version)\n"
        "  case \"$4\" in\n"
        "    26.1.11_10) case \"$3\" in 26.1.11_10|26.1.1[2-9]*|26.[2-9]*|2[7-9].*) printf '>\\n';; *) printf '<\\n';; esac;;\n"
        "    9.20.26) case \"$3\" in 9.20.26*|9.20.27*|9.21*) printf '>\\n';; *) printf '<\\n';; esac;;\n"
        "    *) exit 64;;\n"
        "  esac;;\n"
        "rquery) printf '%s\\n' 'bind920|9.20.26_1|dns/bind920' 'bind-tools|9.20.26_1|dns/bind-tools';;\n"
        "update) ;;\n"
        "install) case \"$*\" in *resolver-plugins-bind920*) : > \"$RP_TEST_FALLBACK_MARKER\";; esac;;\n"
        "*) exit 64;;\n"
        "esac\n",
    )


def run_installer(tmp_path: Path, **kwargs: object) -> tuple[subprocess.CompletedProcess[str], Path, Path]:
    environment, log, repositories = installer_environment(tmp_path, **kwargs)
    FIXTURE_ROOT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(dir=FIXTURE_ROOT) as fixture_directory:
        fixtures = Path(fixture_directory)
        write_command_fixtures(fixtures)
        environment["PATH"] = f"{fixtures}:{environment['PATH']}"
        result = subprocess.run(
            ["/bin/sh", INSTALLER], text=True, capture_output=True, check=False, env=environment
        )
    return result, log, repositories


def test_installs_current_plugin_for_the_detected_series_without_service_changes(tmp_path: Path) -> None:
    result, log, repositories = run_installer(tmp_path)

    assert result.returncode == 0, result.stderr
    assert "pkg-26.1" in (repositories / "resolver-plugins.conf").read_text(encoding="utf-8")
    calls = log.read_text(encoding="utf-8")
    assert "pkg install -y -r resolver-plugins os-bind-rp" in calls
    assert "resolver-plugins-bind920" not in calls
    assert "configctl" not in calls
    assert "service" not in calls


def test_uses_eligible_opnsense_bind_without_prompting_or_fallback(tmp_path: Path) -> None:
    result, log, _ = run_installer(tmp_path)

    assert result.returncode == 0, result.stderr
    assert "Do you wish to update BIND?" not in result.stderr
    calls = log.read_text(encoding="utf-8")
    assert "pkg install -y -r resolver-plugins-bind920" not in calls


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
    assert "Available fallback: bind920 9.20.26_1 and bind-tools 9.20.26_1" in result.stderr
    assert "An update to BIND is required to address a breaking issue with DoT." in result.stderr
    assert (
        "Note: future OPNsense updates to BIND will still work as long as they are above the pinned version."
        in result.stderr
    )
    assert "Do you wish to update BIND? [y/N]" in result.stderr
    fallback = (repositories / "resolver-plugins-bind920.conf").read_text(encoding="utf-8")
    assert "enabled: no" in fallback
    calls = log.read_text(encoding="utf-8")
    assert calls.index("pkg install -y -r resolver-plugins-bind920 bind920 bind-tools") < calls.index(
        "pkg install -y -r resolver-plugins os-bind-rp"
    )


def test_prompts_when_bind_tools_are_missing_or_from_the_wrong_origin(tmp_path: Path) -> None:
    result, _, _ = run_installer(
        tmp_path,
        bind_tools="bind-tools|9.20.26_1|resolver/bind-tools",
        confirmation="n",
    )

    assert result.returncode != 0
    assert "Installed bind920: bind920 9.20.26_1 from dns/bind920" in result.stderr
    assert "Installed bind-tools: bind-tools 9.20.26_1 from resolver/bind-tools" in result.stderr
    assert "BIND update declined; os-bind-rp was not installed." in result.stderr


def test_declining_bind_fallback_leaves_the_plugin_uninstalled(tmp_path: Path) -> None:
    result, log, _ = run_installer(
        tmp_path, bind920="bind920|9.20.25|dns/bind920", confirmation="n"
    )

    assert result.returncode != 0
    assert "BIND update declined; os-bind-rp was not installed." in result.stderr
    calls = log.read_text(encoding="utf-8")
    assert "pkg install -y -r resolver-plugins-bind920 bind920 bind-tools" not in calls
    assert "pkg install -y -r resolver-plugins os-bind-rp" not in calls


def test_rejects_an_unsupported_opnsense_series(tmp_path: Path) -> None:
    result, _, _ = run_installer(tmp_path, opnsense_version="OPNsense 25.7.2 (amd64)")

    assert result.returncode != 0
    assert "unsupported OPNsense release series: 25.7" in result.stderr
