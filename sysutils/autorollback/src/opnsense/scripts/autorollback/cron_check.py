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
OPNsense Auto Rollback - Cron Safety Net

Called by cron every minute. If safe mode is pending and the timer
daemon died, this catches the expired timer and triggers the rollback
the daemon should have run.

Usage: cron_check.py (no arguments, called by cron)
"""

import json
import os
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib.common import (
    log_info, log_warning, log_error,
    ensure_volatile_dir,
    read_persistent_state, clear_persistent_state, clear_session_token,
    is_restore_in_progress, is_firmware_update_running,
    read_timer_pid, clean_timer_pid,
)


def check_safe_mode_expired():
    """
    Cron safety net: check if the safe mode timer expired but the daemon died.
    The secondary trigger; it catches crashed timer daemons.
    """
    state = read_persistent_state()
    if state is None or state.get('mode') != 'safemode':
        return False

    expiry = state.get('expiry_time', 0)
    now = time.time()

    if now < expiry:
        # Not expired: make sure the timer daemon is still alive
        if read_timer_pid() is None:
            remaining = int(expiry - now)
            log_warning('Safe mode timer daemon died with %d seconds remaining. Restarting timer.' % remaining)
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
                # Don't write the PID here: the daemon writes its own after double-fork.
                # The Popen PID belongs to the pre-fork process, which exits at once.
                log_info('Timer daemon restarted with %d seconds remaining' % remaining)
            except Exception as e:
                log_error('Failed to restart timer daemon: %s' % str(e))
        return False

    # Timer expired and the daemon is gone: roll back
    log_warning('Cron safety net: safe mode expired %d seconds ago and the timer daemon is missing. Triggering rollback.' % (
        int(now - expiry)))

    backup_file = state.get('backup_file', '')
    rollback_method = state.get('rollback_method', 'reboot')

    if not backup_file or not os.path.isfile(backup_file):
        log_error('Cannot rollback: backup file missing: %s' % backup_file)
        clear_persistent_state()
        clear_session_token()
        return True

    # Clearing state first prevents re-entrancy
    clear_persistent_state()
    clear_session_token()
    clean_timer_pid()

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


def main():
    result = {'status': 'ok'}

    # Skip if restore is in progress (re-entrancy guard)
    if is_restore_in_progress():
        result['message'] = 'Restore in progress, skipping check'
        print(json.dumps(result))
        return

    # Skip during firmware updates
    if is_firmware_update_running():
        result['message'] = 'Firmware update in progress, skipping check'
        print(json.dumps(result))
        return

    if check_safe_mode_expired():
        result['message'] = 'Safe mode expired; safety net triggered rollback'
    else:
        result['message'] = 'Safety net check completed'
    print(json.dumps(result))


if __name__ == '__main__':
    ensure_volatile_dir()
    main()
