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
OPNsense Auto Rollback - Common library
Shared constants, state management, and utility functions.

State architecture:
  - Volatile state (cleared on reboot): /var/run/autorollback/
    * timer PID, active session flag, confirmation token
  - Persistent state (survives reboot): /conf/autorollback_pending.json
    * known-good backup path, expiry timestamp (for early-boot recovery)
"""

import json
import os
import re
import sys
import time
import fcntl
import glob
import ipaddress
import shlex
import signal
import subprocess
import syslog
import secrets
import tempfile
import xml.etree.ElementTree as ET

# --- Path constants ---
VOLATILE_DIR = '/var/run/autorollback'
PERSISTENT_STATE_FILE = '/conf/autorollback_pending.json'
TIMER_PID_FILE = os.path.join(VOLATILE_DIR, 'timer.pid')
RESTORE_LOCK_FILE = os.path.join(VOLATILE_DIR, 'restoring.lock')
SESSION_TOKEN_FILE = os.path.join(VOLATILE_DIR, 'session.token')
WATCHDOG_FAIL_COUNT_FILE = os.path.join(VOLATILE_DIR, 'watchdog_failures')
WATCHDOG_LAST_CONFIG_FILE = os.path.join(VOLATILE_DIR, 'last_config_change')

CONFIG_XML = '/conf/config.xml'
CONFIG_BACKUP_DIR = '/conf/backup'
CONFIG_CACHE = '/tmp/config.cache'

# Firmware update indicators
FIRMWARE_LOCK = '/tmp/pkg_upgrade.progress'
FIRMWARE_PROCS = ['opnsense-update', 'opnsense-bootstrap', 'opnsense-patch']

# Regex for valid timestamped backup filenames
BACKUP_TIMESTAMP_RE = re.compile(r'^config-\d+(\.\d+)?(_\d+)?\.xml$')


# --- Syslog setup (open once at module load, never close) ---
syslog.openlog('autorollback', syslog.LOG_PID, syslog.LOG_LOCAL4)

def log_info(msg):
    syslog.syslog(syslog.LOG_INFO, msg)

def log_warning(msg):
    syslog.syslog(syslog.LOG_WARNING, msg)

def log_error(msg):
    syslog.syslog(syslog.LOG_ERR, msg)


# --- Directory management ---
def ensure_volatile_dir():
    """Create the volatile state directory if it doesn't exist."""
    os.makedirs(VOLATILE_DIR, mode=0o750, exist_ok=True)


# --- Settings reader (single source of truth) ---
def read_model_settings():
    """Read all plugin settings from config.xml. Used by all scripts."""
    defaults = {
        'enabled': False,
        'timeout': 120,
        'rollback_method': 'reboot',
        'watchdog_enabled': False,
        'grace_period': 60,
        'fail_threshold': 3,
        'check_command': 'ping -c 1 -W 3 -t 5 %gateway%',
        'check_pattern': '1 packets received',
        'check_command_2': '',
        'check_pattern_2': '',
        'log_rollbacks': True,
    }
    try:
        tree = ET.parse(CONFIG_XML)
        root = tree.getroot()
        ar = root.find('.//OPNsense/autorollback/general')
        if ar is not None:
            return {
                'enabled': (ar.findtext('Enabled', '0') == '1'),
                'timeout': int(ar.findtext('SafeModeTimeout', '120')),
                'rollback_method': ar.findtext('RollbackMethod', 'reboot'),
                'watchdog_enabled': (ar.findtext('WatchdogEnabled', '0') == '1'),
                'grace_period': int(ar.findtext('WatchdogGracePeriod', '60')),
                'fail_threshold': int(ar.findtext('WatchdogFailThreshold', '3')),
                'check_command': ar.findtext('WatchdogCheckCommand',
                                             'ping -c 1 -W 3 -t 5 %gateway%'),
                'check_pattern': ar.findtext('WatchdogCheckPattern',
                                             '1 packets received'),
                'check_command_2': ar.findtext('WatchdogCheckCommand2', ''),
                'check_pattern_2': ar.findtext('WatchdogCheckPattern2', ''),
                'log_rollbacks': (ar.findtext('LogRollbacks', '1') == '1'),
            }
    except Exception as e:
        log_warning('Could not read model settings: %s' % str(e))
    return defaults


# --- Persistent state management ---
def read_persistent_state():
    """Read the persistent state file. Returns dict or None."""
    try:
        if os.path.isfile(PERSISTENT_STATE_FILE):
            with open(PERSISTENT_STATE_FILE, 'r') as f:
                return json.load(f)
    except (json.JSONDecodeError, IOError, OSError) as e:
        log_warning('Failed to read persistent state: %s' % str(e))
    return None

def write_persistent_state(state):
    """Write persistent state atomically using temp file + rename."""
    dir_name = os.path.dirname(PERSISTENT_STATE_FILE)
    fd_num = None
    tmp_path = None
    try:
        fd_num, tmp_path = tempfile.mkstemp(dir=dir_name, prefix='.autorollback_')
        with os.fdopen(fd_num, 'w') as f:
            fd_num = None  # os.fdopen takes ownership
            json.dump(state, f, indent=2)
            f.flush()
            os.fsync(f.fileno())
        os.rename(tmp_path, PERSISTENT_STATE_FILE)
        tmp_path = None  # Rename succeeded
    except (IOError, OSError) as e:
        log_error('Failed to write persistent state: %s' % str(e))
        if tmp_path and os.path.isfile(tmp_path):
            os.unlink(tmp_path)
        raise
    finally:
        if fd_num is not None:
            os.close(fd_num)

def clear_persistent_state():
    """Remove the persistent state file."""
    try:
        if os.path.isfile(PERSISTENT_STATE_FILE):
            os.unlink(PERSISTENT_STATE_FILE)
    except OSError:
        pass


# --- Session token management ---
def generate_session_token():
    """Generate a cryptographically random session token for safe mode."""
    token = secrets.token_hex(32)
    ensure_volatile_dir()
    fd = os.open(SESSION_TOKEN_FILE, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    try:
        os.write(fd, token.encode())
    finally:
        os.close(fd)
    return token

def read_session_token():
    """Read the current session token, or None."""
    try:
        if os.path.isfile(SESSION_TOKEN_FILE):
            with open(SESSION_TOKEN_FILE, 'r') as f:
                return f.read().strip()
    except (IOError, OSError):
        pass
    return None

def clear_session_token():
    """Remove the session token file."""
    try:
        if os.path.isfile(SESSION_TOKEN_FILE):
            os.unlink(SESSION_TOKEN_FILE)
    except OSError:
        pass


# --- Re-entrancy guard ---
def is_restore_in_progress():
    """Check if a restore operation is currently running (re-entrancy guard)."""
    if not os.path.isfile(RESTORE_LOCK_FILE):
        return False
    fd = None
    try:
        fd = open(RESTORE_LOCK_FILE, 'r')
        try:
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
            # We got the lock — nobody holds it, stale file
            fcntl.flock(fd, fcntl.LOCK_UN)
            try:
                os.unlink(RESTORE_LOCK_FILE)
            except OSError:
                pass
            return False
        except (BlockingIOError, OSError):
            return True  # Lock held — restore in progress
    except (IOError, OSError):
        return False
    finally:
        if fd is not None:
            fd.close()

def acquire_restore_lock():
    """Acquire the restore lock. Returns file descriptor or None."""
    ensure_volatile_dir()
    try:
        fd = open(RESTORE_LOCK_FILE, 'w')
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        fd.write(str(os.getpid()))
        fd.flush()
        return fd
    except (BlockingIOError, IOError, OSError):
        return None

def release_restore_lock(fd):
    """Release the restore lock."""
    try:
        fcntl.flock(fd, fcntl.LOCK_UN)
        fd.close()
        if os.path.isfile(RESTORE_LOCK_FILE):
            os.unlink(RESTORE_LOCK_FILE)
    except (IOError, OSError):
        pass


# --- Timer PID management ---
def read_timer_pid():
    """Read the PID of the running background timer, or None."""
    try:
        if os.path.isfile(TIMER_PID_FILE):
            with open(TIMER_PID_FILE, 'r') as f:
                pid = int(f.read().strip())
            # Check if process is still alive
            os.kill(pid, 0)
            return pid
    except (ValueError, ProcessLookupError, PermissionError, IOError, OSError):
        clean_timer_pid()
    return None

def write_timer_pid(pid):
    """Store the timer process PID."""
    ensure_volatile_dir()
    with open(TIMER_PID_FILE, 'w') as f:
        f.write(str(pid))

def clean_timer_pid():
    """Remove the timer PID file."""
    try:
        if os.path.isfile(TIMER_PID_FILE):
            os.unlink(TIMER_PID_FILE)
    except OSError:
        pass


# --- Kill running timer ---
def kill_timer():
    """Kill the background timer process if running."""
    pid = read_timer_pid()
    if pid is not None:
        try:
            os.kill(pid, signal.SIGTERM)
            for _ in range(10):
                time.sleep(0.1)
                try:
                    os.kill(pid, 0)
                except ProcessLookupError:
                    break
            else:
                try:
                    os.kill(pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
        except (ProcessLookupError, PermissionError):
            pass
    clean_timer_pid()


# --- Safe mode state queries ---
def is_safe_mode_active():
    """Check if safe mode is currently active."""
    state = read_persistent_state()
    if state is None:
        return False
    if state.get('mode') != 'safemode':
        return False
    if read_timer_pid() is not None:
        return True
    expiry = state.get('expiry_time', 0)
    if time.time() < expiry:
        return True
    return False

def get_safe_mode_info():
    """Get full safe mode status information. Always returns all keys."""
    state = read_persistent_state()
    default = {
        'active': False,
        'mode': 'idle',
        'backup_file': '',
        'backup_revision': '',
        'start_time': 0,
        'expiry_time': 0,
        'remaining_seconds': 0,
        'timeout': 0,
        'rollback_method': 'reboot',
        'timer_pid': None,
        'token': None,
    }
    if state is None:
        return default

    now = time.time()
    expiry = state.get('expiry_time', 0)
    remaining = max(0, expiry - now)

    return {
        'active': state.get('mode') == 'safemode' and (
            remaining > 0 or read_timer_pid() is not None),
        'mode': state.get('mode', 'idle'),
        'backup_file': state.get('backup_file', ''),
        'backup_revision': state.get('backup_revision', ''),
        'start_time': state.get('start_time', 0),
        'expiry_time': expiry,
        'remaining_seconds': int(remaining),
        'timeout': state.get('timeout', 0),
        'rollback_method': state.get('rollback_method', 'reboot'),
        'timer_pid': read_timer_pid(),
        'token': read_session_token(),
    }


# --- Firmware update detection ---
def is_firmware_update_running():
    """Check if a firmware update is in progress."""
    if os.path.isfile(FIRMWARE_LOCK):
        return True
    try:
        for proc_name in FIRMWARE_PROCS:
            result = subprocess.run(
                ['pgrep', '-x', proc_name],  # -x = exact match on process name
                capture_output=True, timeout=5
            )
            if result.returncode == 0:
                return True
    except (subprocess.TimeoutExpired, OSError):
        pass
    return False


# --- Config backup helpers ---
def get_latest_backup():
    """Get the path of the most recent timestamped config backup."""
    backups = glob.glob(os.path.join(CONFIG_BACKUP_DIR, 'config-*.xml'))
    # Only consider timestamped backups, not safety backups like config-pre-rollback.xml
    backups = [b for b in backups if BACKUP_TIMESTAMP_RE.match(os.path.basename(b))]
    backups.sort()
    if backups:
        return backups[-1]
    return None

def get_previous_backup():
    """Get the second-most-recent timestamped backup (the one BEFORE the latest)."""
    backups = glob.glob(os.path.join(CONFIG_BACKUP_DIR, 'config-*.xml'))
    backups = [b for b in backups if BACKUP_TIMESTAMP_RE.match(os.path.basename(b))]
    backups.sort()
    if len(backups) >= 2:
        return backups[-2]
    return None

def get_backup_revision(backup_path):
    """Extract the revision timestamp from a backup filename."""
    basename = os.path.basename(backup_path)
    if basename.startswith('config-') and basename.endswith('.xml'):
        return basename[7:-4]
    return None


# --- Gateway detection ---
def get_default_gateway():
    """Get the default gateway IP from the routing table. Returns validated IP string."""
    try:
        result = subprocess.run(
            ['route', '-n', 'get', 'default'],
            capture_output=True, text=True, timeout=5
        )
        for line in result.stdout.splitlines():
            line = line.strip()
            if line.startswith('gateway:'):
                gw = line.split(':', 1)[1].strip()
                # Validate it's a real IP address (prevents injection)
                ipaddress.ip_address(gw)
                return gw
    except (subprocess.TimeoutExpired, OSError, ValueError, IndexError):
        pass
    return None


# --- Configd helper ---
def configctl(cmd, timeout=60):
    """Run a configctl command. Uses shlex for safe argument splitting."""
    try:
        if os.path.exists('/var/run/configd.socket'):
            result = subprocess.run(
                ['configctl'] + shlex.split(cmd),
                capture_output=True, text=True, timeout=timeout
            )
            return result.returncode == 0, result.stdout.strip()
        else:
            log_warning('configd socket not available, skipping configctl: %s' % cmd)
            return False, 'configd unavailable'
    except (subprocess.TimeoutExpired, OSError) as e:
        log_warning('configctl failed for "%s": %s' % (cmd, str(e)))
        return False, str(e)
