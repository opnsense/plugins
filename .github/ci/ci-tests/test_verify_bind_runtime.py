import os
from pathlib import Path
import subprocess
import textwrap

import pytest


ROOT = Path(__file__).resolve().parents[3]
VERIFIER = ROOT / ".github" / "ci" / "verify-bind-runtime.sh"


FAKE_COMMANDS = r"""
set -eu

log_command() {
    command_name=$1
    shift
    {
        printf '%s' "$command_name"
        for argument in "$@"
        do
            printf '|%s' "$argument"
        done
        printf '\n'
    } >> "$FAKE_STATE/commands.log"
}

mktemp() {
    log_command mktemp "$@"
    mkdir "$FAKE_STATE/runtime"
    printf '%s\n' "$FAKE_STATE/runtime"
}

sysrc() {
    log_command sysrc "$@"
    if [ "${1-}" != -s ] || [ "${2-}" != named ]
    then
        return 96
    fi
    shift 2
    if [ "${1-}" = -N ] && [ "${2-}" = -A ]
    then
        if [ "$FAIL_INITIAL_QUERY" = yes ]
        then
            return 1
        fi
        if [ "$ORIGINAL_ENABLE_PRESENT" = yes ]
        then
            printf 'named_enable\n'
        fi
        if [ "$ORIGINAL_CONF_PRESENT" = yes ]
        then
            printf 'named_conf\n'
        fi
        return 0
    fi
    if [ "${1-}" = -n ]
    then
        case "${2-}" in
            named_enable)
                if [ "$ORIGINAL_ENABLE_PRESENT" = yes ]
                then
                    if [ "$FAIL_ENABLE_READ" = yes ]
                    then
                        return 1
                    fi
                    printf '%s\n' "$ORIGINAL_ENABLE_VALUE"
                else
                    return 1
                fi
                ;;
            named_conf)
                if [ "$ORIGINAL_CONF_PRESENT" = yes ]
                then
                    if [ "$FAIL_CONF_READ" = yes ]
                    then
                        return 1
                    fi
                    printf '%s\n' "$ORIGINAL_CONF_VALUE"
                else
                    return 1
                fi
                ;;
            named_flags)
                return 97
                ;;
            *)
                return 98
                ;;
        esac
    elif [ "${1-}" = "named_conf=$ORIGINAL_CONF_VALUE" ] && \
        [ "$FAIL_CONF_RESTORE" = yes ]
    then
        return 1
    fi
}

service() {
    log_command service "$@"
    if [ "${1-}" = named ] && [ "${2-}" = onestart ]
    then
        : > "$FAKE_STATE/runtime/rndc.key"
    fi
    if [ "${1-}" = named ] && [ "${2-}" = onestart ] && \
        [ "$PARTIAL_START_FAILURE" = yes ]
    then
        return 1
    fi
}

drill() {
    log_command drill "$@"
    if [ "$CANARY_SUCCESS" = yes ]
    then
        printf 'canary.invalid. 60 IN A 192.0.2.53\n'
    else
        return 1
    fi
}

chown() {
    log_command chown "$@"
}

sleep() {
    log_command sleep "$@"
}

. "$VERIFIER_PATH"
"""


def run_verifier(
    tmp_path,
    *,
    original_conf_present,
    canary_success,
    fail_conf_restore=False,
    fail_conf_read=False,
    fail_enable_read=False,
    fail_initial_query=False,
    original_conf_value="/original/named.conf",
    original_enable_present=True,
    original_enable_value="NO",
    partial_start_failure=False,
):
    fake_bin = tmp_path / "bin"
    fake_state = tmp_path / "state"
    fake_bin.mkdir()
    fake_state.mkdir()
    (fake_bin / "named-checkconf").symlink_to("/bin/true")

    environment = os.environ.copy()
    environment.update(
        {
            "CANARY_SUCCESS": "yes" if canary_success else "no",
            "FAIL_CONF_RESTORE": "yes" if fail_conf_restore else "no",
            "FAIL_CONF_READ": "yes" if fail_conf_read else "no",
            "FAIL_ENABLE_READ": "yes" if fail_enable_read else "no",
            "FAIL_INITIAL_QUERY": "yes" if fail_initial_query else "no",
            "FAKE_STATE": str(fake_state),
            "ORIGINAL_CONF_PRESENT": "yes" if original_conf_present else "no",
            "ORIGINAL_CONF_VALUE": original_conf_value,
            "ORIGINAL_ENABLE_PRESENT": "yes" if original_enable_present else "no",
            "ORIGINAL_ENABLE_VALUE": original_enable_value,
            "PARTIAL_START_FAILURE": "yes" if partial_start_failure else "no",
            "PATH": f"{fake_bin}:{environment['PATH']}",
            "VERIFIER_PATH": str(VERIFIER),
        }
    )
    result = subprocess.run(
        ["/bin/sh", "-c", textwrap.dedent(FAKE_COMMANDS)],
        check=False,
        capture_output=True,
        env=environment,
        text=True,
    )
    command_log = fake_state / "commands.log"
    assert command_log.is_file(), result.stderr
    commands = command_log.read_text(encoding="utf-8").splitlines()
    return result, commands, fake_state


def test_runtime_verifier_checks_config_restarts_managed_bind_and_answers_a_query():
    assert VERIFIER.is_file()
    assert VERIFIER.stat().st_mode & 0o111
    text = VERIFIER.read_text(encoding="utf-8")
    assert "named-checkconf" in text
    assert "service named onerestart" in text
    assert "drill" in text
    assert "canary.invalid" in text
    assert "192.0.2.53" in text
    assert "trap" in text


@pytest.mark.parametrize("original_conf_present", [True, False])
@pytest.mark.parametrize("canary_success", [True, False])
def test_runtime_verifier_restores_named_conf_on_every_exit(
    tmp_path, original_conf_present, canary_success
):
    result, commands, fake_state = run_verifier(
        tmp_path,
        original_conf_present=original_conf_present,
        canary_success=canary_success,
    )

    assert (result.returncode == 0) is canary_success
    if original_conf_present:
        assert "sysrc|-s|named|named_conf=/original/named.conf" in commands
    else:
        assert "sysrc|-s|named|-x|named_conf" in commands
    assert "sysrc|-s|named|named_enable=NO" in commands
    assert "service|named|onestop" in commands
    enable_index = commands.index("sysrc|-s|named|named_enable=YES")
    conf_index = commands.index(
        f"sysrc|-s|named|named_conf={fake_state / 'runtime' / 'named.conf'}"
    )
    start_index = commands.index("service|named|onestart")
    assert enable_index < conf_index < start_index
    assert not (fake_state / "runtime").exists()
    assert all("named_flags" not in command for command in commands)
    assert all(
        command.startswith("sysrc|-s|named|")
        for command in commands
        if command.startswith("sysrc|")
    )


def test_runtime_verifier_reports_restore_failure_and_finishes_cleanup(tmp_path):
    result, commands, fake_state = run_verifier(
        tmp_path,
        original_conf_present=True,
        canary_success=True,
        fail_conf_restore=True,
    )

    assert result.returncode != 0
    assert "service|named|onestop" in commands
    assert "sysrc|-s|named|named_conf=/original/named.conf" in commands
    assert "sysrc|-s|named|named_enable=NO" in commands
    assert not (fake_state / "runtime").exists()
    assert all("named_flags" not in command for command in commands)


def test_runtime_verifier_removes_temporary_enable_setting_when_initially_absent(
    tmp_path,
):
    result, commands, _ = run_verifier(
        tmp_path,
        original_conf_present=False,
        original_enable_present=False,
        canary_success=True,
    )

    assert result.returncode == 0
    assert "sysrc|-s|named|-x|named_enable" in commands


def test_runtime_verifier_preserves_present_empty_rc_settings(tmp_path):
    result, commands, _ = run_verifier(
        tmp_path,
        original_conf_present=True,
        original_conf_value="",
        original_enable_present=True,
        original_enable_value="",
        canary_success=True,
    )

    assert result.returncode == 0
    assert "sysrc|-s|named|named_conf=" in commands
    assert "sysrc|-s|named|named_enable=" in commands


def test_runtime_verifier_stops_after_partial_start_failure(tmp_path):
    result, commands, _ = run_verifier(
        tmp_path,
        original_conf_present=False,
        canary_success=True,
        partial_start_failure=True,
    )

    assert result.returncode != 0
    assert "service|named|onestart" in commands
    assert "service|named|onestop" in commands
    assert "sysrc|-s|named|-x|named_conf" in commands
    assert "sysrc|-s|named|named_enable=NO" in commands
    assert all("named_flags" not in command for command in commands)


def test_runtime_verifier_fails_before_mutation_when_initial_query_fails(tmp_path):
    result, commands, _ = run_verifier(
        tmp_path,
        original_conf_present=True,
        canary_success=True,
        fail_initial_query=True,
    )

    assert result.returncode != 0
    assert commands == ["sysrc|-s|named|-N|-A"]


@pytest.mark.parametrize("failed_read", ["enable", "conf"])
def test_runtime_verifier_fails_before_mutation_when_value_read_fails(
    tmp_path, failed_read
):
    result, commands, _ = run_verifier(
        tmp_path,
        original_conf_present=True,
        canary_success=True,
        fail_enable_read=failed_read == "enable",
        fail_conf_read=failed_read == "conf",
    )

    assert result.returncode != 0
    assert commands[0] == "sysrc|-s|named|-N|-A"
    assert f"sysrc|-s|named|-n|named_{failed_read}" in commands
    assert all(not command.startswith("mktemp|") for command in commands)
    assert all(not command.startswith("service|") for command in commands)
    assert all(
        "=" not in command and "|-x|" not in command
        for command in commands
        if command.startswith("sysrc|")
    )
