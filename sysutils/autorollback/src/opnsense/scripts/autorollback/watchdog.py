#!/usr/local/bin/python3
"""
    Copyright (c) 2026 MP Lindsey
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""
"""
OPNsense Auto Rollback - Connectivity Watchdog

Called by cron every minute. This is Layer 2 of the safety system:
  Layer 1: Timer daemon (primary, second-precise)
  Layer 2: This watchdog (secondary, minute-precise)
  Layer 3: Early boot recovery (tertiary, crash recovery)

This script has TWO functions:

1. CRON SAFETY NET for Safe Mode:
   If the timer daemon died but safe mode state is still pending and expired,
   trigger rollback. This catches the case where the timer process crashed.

2. CONNECTIVITY WATCHDOG (always-on):
   After any config change, run health checks. If checks fail N consecutive
   times within the grace period after a config change, rollback to the
   last known-good config.

Usage: watchdog.py (no arguments, called by cron)
"""

import json
import os
import re
import shlex
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib.common import (
    log_info, log_warning, log_error,
    read_model_settings, ensure_volatile_dir,
    read_persistent_state, clear_persistent_state, clear_session_token,
    is_restore_in_progress, is_firmware_update_running,
    get_default_gateway, get_previous_backup,
    read_timer_pid, kill_timer, clean_timer_pid,
    write_timer_pid,
    VOLATILE_DIR, WATCHDOG_FAIL_COUNT_FILE, WATCHDOG_LAST_CONFIG_FILE,
    CONFIG_XML
)


def get_fail_count():
    """Read the consecutive failure count."""
    try:
        if os.path.isfile(WATCHDOG_FAIL_COUNT_FILE):
            with open(WATCHDOG_FAIL_COUNT_FILE, 'r') as f:
                return int(f.read().strip())
    except (ValueError, IOError):
        pass
    return 0


def set_fail_count(count):
    """Write the consecutive failure count."""
    try:
        with open(WATCHDOG_FAIL_COUNT_FILE, 'w') as f:
            f.write(str(count))
    except IOError:
        pass


def clear_fail_count():
    """Reset the failure counter."""
    try:
        if os.path.isfile(WATCHDOG_FAIL_COUNT_FILE):
            os.unlink(WATCHDOG_FAIL_COUNT_FILE)
    except OSError:
        pass


def get_last_config_change():
    """Read the last config change record (time, new backup, previous backup)."""
    try:
        if os.path.isfile(WATCHDOG_LAST_CONFIG_FILE):
            with open(WATCHDOG_LAST_CONFIG_FILE, 'r') as f:
                data = json.load(f)
                return (
                    data.get('time', 0),
                    data.get('backup', ''),
                    data.get('previous_backup', ''),
                )
    except (json.JSONDecodeError, IOError):
        pass
    return 0, '', ''


def run_health_check(command, pattern, gateway=None):
    """
    Run a health check command and match its output against a pattern.
    Returns (passed, output).

    Security: gateway is already validated by get_default_gateway() via
    ipaddress.ip_address(). We still use shlex.quote() for defense-in-depth
    since the command runs with shell=True.
    """
    if not command:
        return True, 'No command configured'

    # Substitute %gateway% placeholder with safely quoted value
    if '%gateway%' in command:
        if gateway:
            command = command.replace('%gateway%', shlex.quote(gateway))
        else:
            # No gateway available, skip this check
            return True, 'No gateway available, skipping check'

    try:
        result = subprocess.run(
            command, shell=True,
            capture_output=True, text=True, timeout=15
        )
        output = result.stdout + result.stderr

        if pattern:
            try:
                if re.search(pattern, output):
                    return True, output.strip()[:200]
                else:
                    return False, 'Pattern "%s" not found in output' % pattern
            except re.error as e:
                log_warning('Watchdog: invalid regex pattern "%s": %s — treating as pass' % (pattern, e))
                return True, 'Invalid pattern (skipped)'
        else:
            # No pattern — just check exit code
            return result.returncode == 0, output.strip()[:200]

    except subprocess.TimeoutExpired:
        return False, 'Command timed out after 15 seconds'
    except Exception as e:
        return False, 'Command error: %s' % str(e)


def check_safe_mode_expired():
    """
    CRON SAFETY NET: Check if safe mode timer expired but daemon died.
    This is the secondary trigger — catches crashed timer daemons.
    """
    state = read_persistent_state()
    if state is None or state.get('mode') != 'safemode':
        return False

    expiry = state.get('expiry_time', 0)
    now = time.time()

    if now < expiry:
        # Not expired yet — check if timer daemon is still alive
        if read_timer_pid() is None:
            remaining = int(expiry - now)
            log_warning('Safe mode timer daemon died! %d seconds remaining. Restarting timer.' % remaining)
            # Restart the timer daemon
            rollback_method = state.get('rollback_method', 'reboot')
            timer_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'timer_daemon.py')
            try:
                proc = subprocess.Popen(
                    [sys.executable, timer_script, str(remaining), rollback_method],
                    stdin=subprocess.DEVNULL,
                    stdout=subprocess.DEVNULL,
                    stderr=subprocess.DEVNULL,
                    start_new_session=True
                )
                # Don't write PID here — the daemon writes its own after double-fork.
                # The Popen PID is the pre-fork process which exits immediately.
                log_info('Timer daemon restarted with %d seconds remaining' % remaining)
            except Exception as e:
                log_error('Failed to restart timer daemon: %s' % str(e))
        return False

    # Timer expired and daemon is not running — we need to rollback!
    log_warning('CRON SAFETY NET: Safe mode expired %d seconds ago. Timer daemon missing. Triggering rollback.' % (
        int(now - expiry)))

    backup_file = state.get('backup_file', '')
    rollback_method = state.get('rollback_method', 'reboot')

    if not backup_file or not os.path.isfile(backup_file):
        log_error('Cannot rollback: backup file missing: %s' % backup_file)
        clear_persistent_state()
        clear_session_token()
        return True

    # Clear state before rollback
    clear_persistent_state()
    clear_session_token()
    clean_timer_pid()

    # Execute rollback
    rollback_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'rollback.py')
    try:
        subprocess.run(
            [sys.executable, rollback_script, backup_file, rollback_method],
            stdin=subprocess.DEVNULL,
            capture_output=True, timeout=300
        )
    except Exception as e:
        log_error('Cron safety net rollback failed: %s' % str(e))

    return True


def run_watchdog(settings):
    """
    CONNECTIVITY WATCHDOG: Run health checks after config changes.
    """
    last_change_time, last_backup, previous_backup = get_last_config_change()

    if last_change_time == 0:
        # No recent config change recorded — nothing to watch
        clear_fail_count()
        return

    now = time.time()
    age = now - last_change_time
    grace = settings['grace_period']

    # Only run checks within the grace period after a config change
    if age > grace + 300:
        # More than grace+5min since last change — stop watching
        clear_fail_count()
        return

    # Still within grace period — skip checks until grace period elapses
    if age < grace:
        clear_fail_count()  # Reset stale count from previous config change
        return

    # Run health checks
    gateway = get_default_gateway()

    check1_ok, check1_msg = run_health_check(
        settings['check_command'], settings['check_pattern'], gateway)

    check2_ok = True
    check2_msg = ''
    if settings.get('check_command_2'):
        check2_ok, check2_msg = run_health_check(
            settings['check_command_2'], settings['check_pattern_2'], gateway)

    all_ok = check1_ok and check2_ok

    if all_ok:
        fails = get_fail_count()
        if fails > 0:
            log_info('Watchdog: health check recovered after %d failures' % fails)
        clear_fail_count()
        return

    # Check failed
    fails = get_fail_count() + 1
    set_fail_count(fails)

    log_warning('Watchdog: health check failed (%d/%d). Check1: %s. Check2: %s' % (
        fails, settings['fail_threshold'],
        check1_msg if not check1_ok else 'OK',
        check2_msg if not check2_ok else 'OK'))

    if fails >= settings['fail_threshold']:
        log_warning('WATCHDOG: Failure threshold reached (%d/%d). Triggering rollback!' % (
            fails, settings['fail_threshold']))

        # Find the correct backup to restore — the one BEFORE the config change
        # that broke connectivity (previous_backup), NOT the new one.
        backup_file = None
        if previous_backup and os.path.isfile(previous_backup):
            backup_file = previous_backup
            log_info('Watchdog: rolling back to pre-change backup: %s' % backup_file)
        else:
            # Fallback: try to find the second-most-recent backup
            backup_file = get_previous_backup()
            if backup_file:
                log_info('Watchdog: rolling back to previous backup: %s' % backup_file)
            else:
                log_error('Watchdog: No suitable backup file available for rollback')
                clear_fail_count()
                return

        rollback_method = settings['rollback_method']
        clear_fail_count()

        # Execute rollback
        rollback_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'rollback.py')
        try:
            subprocess.run(
                [sys.executable, rollback_script, backup_file, rollback_method],
                stdin=subprocess.DEVNULL,
                capture_output=True, timeout=300
            )
        except Exception as e:
            log_error('Watchdog rollback failed: %s' % str(e))


def main():
    result = {'status': 'ok', 'checks': []}

    # Skip if restore is in progress (re-entrancy guard)
    if is_restore_in_progress():
        result['message'] = 'Restore in progress, skipping watchdog'
        print(json.dumps(result))
        return

    # Skip during firmware updates
    if is_firmware_update_running():
        result['message'] = 'Firmware update in progress, skipping watchdog'
        print(json.dumps(result))
        return

    # Check 1: Safe mode cron safety net
    if check_safe_mode_expired():
        result['message'] = 'Safe mode expired — rollback triggered by cron safety net'
        print(json.dumps(result))
        return

    # Check 2: Connectivity watchdog
    settings = read_model_settings()
    if settings['enabled'] and settings['watchdog_enabled']:
        run_watchdog(settings)
        result['message'] = 'Watchdog check completed'
    else:
        result['message'] = 'Watchdog disabled'

    print(json.dumps(result))


if __name__ == '__main__':
    ensure_volatile_dir()
    main()
