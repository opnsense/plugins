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
OPNsense Auto Rollback - Safe Mode Controller

Usage:
    safemode.py start [timeout_seconds]
    safemode.py confirm
    safemode.py cancel
    safemode.py extend [additional_seconds]

Start: Snapshots current config, launches background timer.
Confirm: Accepts changes, kills timer, clears state.
Cancel: Manually triggers rollback immediately.
Extend: Adds time to the countdown.
"""

import json
import os
import sys
import subprocess
import time

# Add parent directory to path for lib imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from lib.common import (
    ensure_volatile_dir, log_info, log_warning, log_error,
    read_model_settings,
    read_persistent_state, write_persistent_state, clear_persistent_state,
    generate_session_token, clear_session_token,
    is_safe_mode_active, get_safe_mode_info,
    is_firmware_update_running, is_restore_in_progress,
    get_latest_backup, get_backup_revision,
    write_timer_pid, kill_timer, read_timer_pid,
    VOLATILE_DIR, CONFIG_XML, CONFIG_BACKUP_DIR
)


def force_config_save():
    """
    Force OPNsense to save the current config, creating a backup.
    We do this to ensure we have a backup of the exact running state.
    Returns the backup path or None.
    """
    try:
        # Use configctl to trigger a config save
        result = subprocess.run(
            ['configctl', 'firmware', 'configure'],
            capture_output=True, text=True, timeout=30
        )

        # Now find the most recent backup
        backup = get_latest_backup()
        if backup:
            log_info('Config backup created: %s' % backup)
            return backup
        else:
            log_error('No backup found after config save')
            return None
    except Exception as e:
        log_error('Failed to force config save: %s' % str(e))
        return None


def _launch_timer_daemon(timeout, rollback_method):
    """Launch the background timer daemon process. Returns (pid, error_msg)."""
    timer_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'timer_daemon.py')
    try:
        proc = subprocess.Popen(
            [sys.executable, timer_script, str(int(timeout)), rollback_method],
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            start_new_session=True  # Detach from parent
        )
        write_timer_pid(proc.pid)
        return proc.pid, None
    except Exception as e:
        return None, str(e)


def start_safe_mode(timeout_override=None):
    """Enter safe mode. Snapshot config and start countdown timer."""
    result = {'status': 'error', 'message': ''}

    # Pre-flight checks
    settings = read_model_settings()
    if not settings['enabled']:
        result['message'] = 'Auto-rollback plugin is disabled. Enable it in System > Auto Rollback.'
        print(json.dumps(result))
        return

    if is_firmware_update_running():
        result['message'] = 'Cannot enter safe mode during a firmware update.'
        print(json.dumps(result))
        return

    if is_restore_in_progress():
        result['message'] = 'A restore operation is already in progress.'
        print(json.dumps(result))
        return

    if is_safe_mode_active():
        info = get_safe_mode_info()
        result['message'] = 'Safe mode is already active (%d seconds remaining).' % info['remaining_seconds']
        result['status'] = 'already_active'
        result.update(info)
        print(json.dumps(result))
        return

    # Determine timeout — use is not None to allow timeout_override=0 edge case
    if timeout_override is not None:
        timeout = timeout_override
    else:
        timeout = settings['timeout']
    timeout = max(30, min(3600, int(timeout)))

    # Step 1: Get the current config as our "known good" backup
    # The most recent backup IS the current running config (saved moments ago)
    backup = get_latest_backup()
    if not backup:
        # Force a save to create one
        backup = force_config_save()
        if not backup:
            result['message'] = 'Failed to create configuration backup.'
            print(json.dumps(result))
            return

    backup_revision = get_backup_revision(backup)
    now = time.time()
    expiry = now + timeout

    # Step 2: Generate session token for the confirmation UI
    token = generate_session_token()

    # Step 3: Write persistent state (survives reboot for early-boot recovery)
    state = {
        'mode': 'safemode',
        'backup_file': backup,
        'backup_revision': backup_revision,
        'start_time': now,
        'expiry_time': expiry,
        'timeout': timeout,
        'rollback_method': settings['rollback_method'],
    }
    write_persistent_state(state)

    # Step 4: Launch background timer process
    pid, err = _launch_timer_daemon(timeout, settings['rollback_method'])
    if pid is None:
        log_error('Failed to start timer daemon: %s' % err)
        clear_persistent_state()
        clear_session_token()
        result['message'] = 'Failed to start countdown timer: %s' % err
        print(json.dumps(result))
        return

    log_info('Safe mode started: timeout=%ds, backup=%s, timer_pid=%d' % (
        timeout, backup, pid))

    # Step 5: Trigger git backup if available
    try:
        subprocess.run(
            ['configctl', 'firmware', 'configure'],
            capture_output=True, timeout=10
        )
    except Exception:
        pass  # Non-critical

    result = {
        'status': 'ok',
        'message': 'Safe mode activated. You have %d seconds to confirm changes.' % timeout,
        'timeout': timeout,
        'remaining_seconds': timeout,
        'expiry_time': expiry,
        'backup_file': backup,
        'backup_revision': backup_revision,
        'token': token,
        'rollback_method': settings['rollback_method'],
    }
    print(json.dumps(result))


def confirm_safe_mode():
    """Confirm changes and exit safe mode gracefully."""
    result = {'status': 'error', 'message': ''}

    if not is_safe_mode_active():
        result['message'] = 'Safe mode is not active.'
        result['status'] = 'not_active'
        print(json.dumps(result))
        return

    # Kill the background timer
    kill_timer()

    # Clear all state
    state = read_persistent_state()
    clear_persistent_state()
    clear_session_token()

    log_info('Safe mode confirmed. Changes accepted. Previous backup: %s' % (
        state.get('backup_file', 'unknown') if state else 'unknown'))

    result = {
        'status': 'ok',
        'message': 'Changes confirmed. Safe mode deactivated.',
    }
    print(json.dumps(result))


def cancel_safe_mode():
    """Cancel changes and rollback immediately."""
    result = {'status': 'error', 'message': ''}

    state = read_persistent_state()
    if state is None or state.get('mode') != 'safemode':
        result['message'] = 'Safe mode is not active.'
        result['status'] = 'not_active'
        print(json.dumps(result))
        return

    # Kill the background timer first
    kill_timer()

    backup_file = state.get('backup_file', '')
    rollback_method = state.get('rollback_method', 'reboot')

    if not backup_file or not os.path.isfile(backup_file):
        clear_persistent_state()
        clear_session_token()
        result['message'] = 'Backup file not found: %s' % backup_file
        print(json.dumps(result))
        return

    log_info('Safe mode cancelled. Rolling back to: %s (method: %s)' % (
        backup_file, rollback_method))

    # Clear state before rollback (important: prevents re-entrancy)
    clear_persistent_state()
    clear_session_token()

    # Execute rollback
    rollback_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'rollback.py')
    try:
        proc_result = subprocess.run(
            [sys.executable, rollback_script, backup_file, rollback_method],
            capture_output=True, text=True, timeout=300
        )
        if proc_result.returncode == 0:
            result = {
                'status': 'ok',
                'message': 'Rollback initiated (method: %s). System is reverting.' % rollback_method,
                'rollback_method': rollback_method,
            }
        else:
            result['message'] = 'Rollback script failed: %s' % proc_result.stderr
    except Exception as e:
        result['message'] = 'Rollback execution failed: %s' % str(e)

    print(json.dumps(result))


def extend_safe_mode(additional_seconds=None):
    """Extend the safe mode countdown timer."""
    result = {'status': 'error', 'message': ''}

    state = read_persistent_state()
    if state is None or state.get('mode') != 'safemode':
        result['message'] = 'Safe mode is not active.'
        result['status'] = 'not_active'
        print(json.dumps(result))
        return

    if additional_seconds is None:
        additional_seconds = 60  # Default extension

    additional_seconds = max(10, min(3600, int(additional_seconds)))

    # Update expiry in persistent state
    new_expiry = state.get('expiry_time', time.time()) + additional_seconds
    state['expiry_time'] = new_expiry
    write_persistent_state(state)

    # Kill old timer and start a new one with remaining time
    kill_timer()
    remaining = int(new_expiry - time.time())
    if remaining > 0:
        rollback_method = state.get('rollback_method', 'reboot')
        pid, err = _launch_timer_daemon(remaining, rollback_method)
        if pid is None:
            log_error('Failed to restart timer: %s' % err)
    else:
        remaining = 0

    log_info('Safe mode extended by %d seconds. New remaining: %d seconds.' % (
        additional_seconds, remaining))

    result = {
        'status': 'ok',
        'message': 'Timer extended by %d seconds. %d seconds remaining.' % (
            additional_seconds, remaining),
        'remaining_seconds': remaining,
        'expiry_time': new_expiry,
    }
    print(json.dumps(result))


if __name__ == '__main__':
    ensure_volatile_dir()

    if len(sys.argv) < 2:
        print(json.dumps({'status': 'error', 'message': 'Usage: safemode.py start|confirm|cancel|extend [args]'}))
        sys.exit(1)

    action = sys.argv[1].lower()

    if action == 'start':
        timeout = None
        if len(sys.argv) > 2:
            try:
                timeout = int(sys.argv[2])
            except ValueError:
                print(json.dumps({'status': 'error', 'message': 'Invalid timeout value: %s' % sys.argv[2]}))
                sys.exit(1)
        start_safe_mode(timeout)
    elif action == 'confirm':
        confirm_safe_mode()
    elif action == 'cancel':
        cancel_safe_mode()
    elif action == 'extend':
        extra = None
        if len(sys.argv) > 2:
            try:
                extra = int(sys.argv[2])
            except ValueError:
                print(json.dumps({'status': 'error', 'message': 'Invalid seconds value: %s' % sys.argv[2]}))
                sys.exit(1)
        extend_safe_mode(extra)
    else:
        print(json.dumps({'status': 'error', 'message': 'Unknown action: %s' % action}))
        sys.exit(1)
