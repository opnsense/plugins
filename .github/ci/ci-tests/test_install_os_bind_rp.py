"""Regression coverage for the interactive os-bind-rp installer."""

from __future__ import annotations

import os
import subprocess
from pathlib import Path


REPOSITORY_ROOT = Path(__file__).resolve().parents[3]
INSTALLER = REPOSITORY_ROOT / "scripts" / "install-os-bind-rp.sh"
PUBLIC_KEY_SHA256 = "bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e"


def installer_environment(
    tmp_path: Path,
    *,
    opnsense_version: str = "OPNsense 26.1.11_10 (amd64)",
    bind920: str = "bind920|9.20.26_1|dns/bind920",
    bind_tools: str = "bind-tools|9.20.26_1|dns/bind-tools",
    confirmation: str | None = None,
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
            "RP_TEST_LOG": str(log),
            "RP_TEST_OPNSENSE_VERSION": opnsense_version,
            "RP_TEST_FALLBACK_MARKER": str(tmp_path / "fallback-installed"),
        }
    )
    return environment, log, tmp_path / "repos"


def run_installer(tmp_path: Path, **kwargs: object) -> tuple[subprocess.CompletedProcess[str], Path, Path]:
    environment, log, repositories = installer_environment(tmp_path, **kwargs)
    driver = r'''
opnsense-version() { printf '%s\n' "$RP_TEST_OPNSENSE_VERSION"; }
fetch() {
    { printf 'fetch'; for argument in "$@"; do printf ' %s' "$argument"; done; printf '\n'; } >> "$RP_TEST_LOG"
    [ "$1" = -o ] || return 64
    printf 'test public key\n' > "$2"
}
sha256() { printf '%s\n' 'bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e'; }
pkg() {
    { printf 'pkg'; for argument in "$@"; do printf ' %s' "$argument"; done; printf '\n'; } >> "$RP_TEST_LOG"
    case "$1" in
        query)
            case "$*" in
                *'%n = bind920'*)
                    if [ -f "$RP_TEST_FALLBACK_MARKER" ]; then printf '%s\n' 'bind920|9.20.26_1|dns/bind920'; else printf '%s\n' "${RP_TEST_BIND920:-}"; fi
                    ;;
                *'%n = bind-tools'*)
                    if [ -f "$RP_TEST_FALLBACK_MARKER" ]; then printf '%s\n' 'bind-tools|9.20.26_1|dns/bind-tools'; else printf '%s\n' "${RP_TEST_BIND_TOOLS:-}"; fi
                    ;;
            esac
            ;;
        version)
            case "$3" in 9.20.26*|9.20.27*|9.21*) printf '>\n' ;; *) printf '<\n' ;; esac
            ;;
        rquery) printf '%s\n' 'bind920|9.20.26_1|dns/bind920' 'bind-tools|9.20.26_1|dns/bind-tools' ;;
        update) ;;
        install)
            case "$*" in *resolver-plugins-bind920*) : > "$RP_TEST_FALLBACK_MARKER" ;; esac
            ;;
        *) return 64 ;;
    esac
}
. "$RP_INSTALLER"
'''
    environment["RP_INSTALLER"] = str(INSTALLER)
    result = subprocess.run(
        ["/bin/bash", "-c", driver], text=True, capture_output=True, check=False, env=environment
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


def test_prompts_for_and_installs_the_fallback_when_bind_is_ineligible(tmp_path: Path) -> None:
    result, log, repositories = run_installer(
        tmp_path, bind920="bind920|9.20.25|dns/bind920", confirmation="y"
    )

    assert result.returncode == 0, result.stderr
    assert "Installed BIND: bind920 9.20.25 from dns/bind920" in result.stderr
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
