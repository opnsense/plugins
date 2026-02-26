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
OPNsense Auto Rollback - Timer Daemon

This is a background process that counts down and triggers rollback
if not killed before expiry. It is the PRIMARY rollback trigger.

Design:
  - Launched by safemode.py start
  - Double-forks to fully detach from configd parent process
  - Sleeps in 1-second intervals (allows responsive cancellation via SIGTERM)
  - On expiry: reads the backup path from persistent state and executes rollback
  - On SIGTERM: exits cleanly (safe mode was confirmed or cancelled)
  - PID is stored in /var/run/autorollback/timer.pid

Usage: timer_daemon.py <timeout_seconds> <rollback_method>
"""

import os
import sys
import signal
import time
import subprocess

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib.common import (
    log_info, log_warning, log_error,
    read_persistent_state, clear_persistent_state, clear_session_token,
    clean_timer_pid, write_timer_pid, VOLATILE_DIR
)

# Global flag for clean shutdown
_shutdown = False


def handle_sigterm(signum, frame):
    """Handle SIGTERM for clean shutdown (safe mode confirmed/cancelled)."""
    global _shutdown
    _shutdown = True


def daemonize():
    """
    Double-fork to fully detach from the parent process (configd).

    This ensures the timer daemon survives even if configd restarts,
    and that configd doesn't block waiting for our exit.
    """
    # First fork — exit parent (returns control to configd)
    pid = os.fork()
    if pid > 0:
        # Parent: exit immediately so configd doesn't block
        os._exit(0)

    # First child: create new session
    os.setsid()

    # Second fork — prevent reacquiring a controlling terminal
    pid = os.fork()
    if pid > 0:
        # First child exits
        os._exit(0)

    # Second child: the actual daemon process
    # Redirect standard file descriptors to /dev/null
    devnull = os.open(os.devnull, os.O_RDWR)
    try:
        os.dup2(devnull, 0)  # stdin
        os.dup2(devnull, 1)  # stdout
        os.dup2(devnull, 2)  # stderr
    finally:
        if devnull > 2:
            os.close(devnull)

    # Update PID file with our actual daemon PID
    write_timer_pid(os.getpid())


def run_timer(timeout, rollback_method):
    """Main timer loop. Counts down and triggers rollback on expiry."""
    global _shutdown

    # Register signal handlers
    signal.signal(signal.SIGTERM, handle_sigterm)
    signal.signal(signal.SIGINT, handle_sigterm)

    log_info('Timer daemon started: timeout=%ds, method=%s, pid=%d' % (
        timeout, rollback_method, os.getpid()))

    # Count down in 1-second intervals
    elapsed = 0
    while elapsed < timeout:
        if _shutdown:
            log_info('Timer daemon received shutdown signal. Exiting cleanly.')
            clean_timer_pid()
            sys.exit(0)

        time.sleep(1)
        elapsed += 1

    # Timer expired! Time to rollback.
    log_warning('SAFE MODE TIMER EXPIRED after %d seconds. Initiating rollback.' % timeout)

    # Read the backup file from persistent state
    state = read_persistent_state()
    if state is None:
        log_error('Timer expired but no persistent state found. Someone else handled it.')
        clean_timer_pid()
        sys.exit(0)

    backup_file = state.get('backup_file', '')
    if not backup_file or not os.path.isfile(backup_file):
        log_error('Timer expired but backup file missing: %s' % backup_file)
        clear_persistent_state()
        clear_session_token()
        clean_timer_pid()
        sys.exit(1)

    # Clear state BEFORE rollback to prevent re-entrancy
    clear_persistent_state()
    clear_session_token()
    clean_timer_pid()

    # Execute rollback
    rollback_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'rollback.py')
    try:
        log_info('Executing rollback: backup=%s, method=%s' % (backup_file, rollback_method))
        result = subprocess.run(
            [sys.executable, rollback_script, backup_file, rollback_method],
            stdin=subprocess.DEVNULL,
            capture_output=True, text=True, timeout=300
        )
        if result.returncode != 0:
            log_error('Rollback script failed: %s' % result.stderr)
            sys.exit(1)
        else:
            log_info('Rollback script completed successfully.')
    except subprocess.TimeoutExpired:
        log_error('Rollback script timed out after 300 seconds.')
        sys.exit(1)
    except Exception as e:
        log_error('Rollback execution failed: %s' % str(e))
        sys.exit(1)


def main():
    if len(sys.argv) < 3:
        print('Usage: timer_daemon.py <timeout_seconds> <rollback_method>', file=sys.stderr)
        sys.exit(1)

    try:
        timeout = int(sys.argv[1])
    except ValueError:
        print('Invalid timeout value: %s' % sys.argv[1], file=sys.stderr)
        sys.exit(1)

    if timeout <= 0:
        print('Timeout must be positive, got: %d' % timeout, file=sys.stderr)
        sys.exit(1)

    rollback_method = sys.argv[2]
    if rollback_method not in ('reboot', 'reload'):
        rollback_method = 'reboot'

    # Double-fork to fully detach from configd
    daemonize()

    # Now running as a proper daemon
    run_timer(timeout, rollback_method)


if __name__ == '__main__':
    main()
