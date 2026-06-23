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
OPNsense Auto Rollback - Rollback Executor

This script performs the actual configuration rollback. It is called by:
  1. timer_daemon.py (on timer expiry)
  2. safemode.py cancel (manual cancel)
  3. watchdog.py (on connectivity failure)
  4. 10-autorollback-recovery (early boot recovery)

Safety features:
  - Acquires exclusive restore lock (prevents re-entrancy)
  - Validates backup file path (must be within /conf/)
  - Validates backup file content before restore
  - Creates safety backup before overwriting config
  - Atomic restore via temp file + rename
  - Preserves original config.xml ownership
  - Removes config cache to force fresh read
  - Supports two rollback methods: full reboot or service reload
  - Falls back to direct script execution if configd is unavailable
  - Logs everything to syslog

Usage: rollback.py <backup_file_path> <rollback_method>
  rollback_method: "reboot" or "reload"
"""

import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import xml.etree.ElementTree as ET

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib.common import (
    log_info, log_warning, log_error,
    acquire_restore_lock, release_restore_lock,
    is_firmware_update_running,
    CONFIG_XML, CONFIG_CACHE, CONFIG_BACKUP_DIR
)

# Allowed directories for backup files (path traversal defense)
ALLOWED_BACKUP_DIRS = (
    os.path.realpath(CONFIG_BACKUP_DIR),
    os.path.realpath('/conf'),
)


def validate_backup_path(path):
    """
    Validate that a backup file path is within allowed directories.
    Prevents path traversal attacks.
    """
    real_path = os.path.realpath(path)
    for allowed_dir in ALLOWED_BACKUP_DIRS:
        if real_path.startswith(allowed_dir + os.sep) or real_path == allowed_dir:
            return True
    return False


def validate_config_xml(path):
    """Validate that a file is a parseable OPNsense config.xml."""
    try:
        tree = ET.parse(path)
        root = tree.getroot()
        # Basic sanity: must have <opnsense> or legacy <pfsense> root
        if root.tag not in ('opnsense', 'pfsense'):
            return False, 'Root element is "%s", expected "opnsense"' % root.tag
        # Must have a system section
        if root.find('system') is None:
            return False, 'Missing <system> section'
        # Must have interfaces
        if root.find('interfaces') is None:
            return False, 'Missing <interfaces> section'
        return True, 'Valid'
    except ET.ParseError as e:
        return False, 'XML parse error: %s' % str(e)
    except Exception as e:
        return False, 'Validation error: %s' % str(e)


def _get_file_ownership(path):
    """Get the uid/gid of an existing file. Returns (uid, gid) or None."""
    try:
        st = os.stat(path)
        return st.st_uid, st.st_gid
    except OSError:
        return None


def restore_config(backup_path):
    """
    Restore a config.xml backup file.

    Strategy:
      1. Validate the backup path and content
      2. Create a safety backup of the CURRENT config (in case rollback makes things worse)
      3. Preserve original file ownership
      4. Copy backup to /conf/config.xml atomically via temp file + rename
      5. Remove config cache
    """
    # Validate path is within allowed directories
    if not validate_backup_path(backup_path):
        msg = 'Backup path outside allowed directories: %s' % backup_path
        log_error(msg)
        return False, msg

    # Validate backup content
    valid, msg = validate_config_xml(backup_path)
    if not valid:
        log_error('Backup validation failed for %s: %s' % (backup_path, msg))
        return False, msg

    # Capture existing ownership before we overwrite
    ownership = _get_file_ownership(CONFIG_XML)

    # Safety backup of current config (last resort recovery)
    safety_backup = os.path.join(CONFIG_BACKUP_DIR, 'config-pre-rollback.xml')
    try:
        if os.path.isfile(CONFIG_XML):
            shutil.copy2(CONFIG_XML, safety_backup)
            log_info('Safety backup created: %s' % safety_backup)
    except Exception as e:
        log_warning('Could not create safety backup: %s' % str(e))
        # Continue anyway — the rollback is more important

    # Restore the config atomically via temp file + rename
    tmp_fd = None
    tmp_path = None
    try:
        conf_dir = os.path.dirname(CONFIG_XML)
        tmp_fd, tmp_path = tempfile.mkstemp(dir=conf_dir, prefix='.config_rollback_')

        # Close the fd from mkstemp, copy file content
        os.close(tmp_fd)
        tmp_fd = None

        shutil.copy2(backup_path, tmp_path)

        # Set permissions — OPNsense expects 0640
        os.chmod(tmp_path, 0o640)

        # Preserve original ownership if we captured it, otherwise use root:wheel
        if ownership:
            uid, gid = ownership
        else:
            try:
                import pwd
                import grp
                uid = pwd.getpwnam('root').pw_uid
                gid = grp.getgrnam('wheel').gr_gid
            except (KeyError, ImportError):
                uid, gid = 0, 0

        try:
            os.chown(tmp_path, uid, gid)
        except PermissionError:
            pass  # Best effort

        os.rename(tmp_path, CONFIG_XML)
        tmp_path = None  # Rename succeeded, don't clean up
        log_info('Configuration restored from: %s' % backup_path)
    except Exception as e:
        log_error('Failed to restore config: %s' % str(e))
        # Clean up failed temp file
        if tmp_path and os.path.isfile(tmp_path):
            try:
                os.unlink(tmp_path)
            except OSError:
                pass
        # Try to restore from safety backup
        if os.path.isfile(safety_backup):
            try:
                shutil.copy2(safety_backup, CONFIG_XML)
                log_info('Restored from safety backup after failed rollback')
            except Exception:
                pass
        return False, 'Failed to restore: %s' % str(e)
    finally:
        if tmp_fd is not None:
            os.close(tmp_fd)

    # Remove config cache so PHP reads fresh config
    try:
        if os.path.isfile(CONFIG_CACHE):
            os.unlink(CONFIG_CACHE)
            log_info('Config cache removed')
    except OSError:
        pass

    return True, 'Configuration restored successfully'


def apply_reboot():
    """Apply configuration by rebooting the system."""
    log_info('ROLLBACK: Initiating full system reboot')
    try:
        # Try configd first
        if os.path.exists('/var/run/configd.socket'):
            subprocess.run(
                ['configctl', 'system', 'reboot'],
                capture_output=True, timeout=10
            )
        else:
            # Direct reboot
            subprocess.Popen(
                ['/usr/local/etc/rc.reboot'],
                stdin=subprocess.DEVNULL,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                start_new_session=True
            )
        return True
    except Exception as e:
        log_error('Reboot command failed: %s' % str(e))
        # Last resort
        try:
            subprocess.Popen(
                ['shutdown', '-r', 'now'],
                stdin=subprocess.DEVNULL,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL
            )
            return True
        except Exception as e2:
            log_error('All reboot methods failed: %s' % str(e2))
            return False


def apply_reload():
    """Apply configuration by reloading all services (no reboot)."""
    log_info('ROLLBACK: Initiating service reload via rc.reload_all')
    try:
        # rc.reload_all accepts a delay parameter
        proc = subprocess.Popen(
            ['/usr/local/etc/rc.reload_all'],
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            start_new_session=True
        )
        # Don't wait for it — it can take a while and we don't want to block
        log_info('rc.reload_all started (pid=%d)' % proc.pid)
        return True
    except Exception as e:
        log_error('rc.reload_all failed: %s' % str(e))
        # Fallback: try individual service restarts
        log_info('Attempting individual service restarts as fallback')
        try:
            if os.path.exists('/var/run/configd.socket'):
                for cmd in ['filter reload', 'interface reconfigure',
                            'dns reload', 'dhcpd restart']:
                    try:
                        subprocess.run(
                            ['configctl'] + cmd.split(),
                            capture_output=True, timeout=30
                        )
                    except Exception:
                        pass
                return True
        except Exception as e2:
            log_error('Fallback service restarts also failed: %s' % str(e2))
        return False


def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: rollback.py <backup_file> <rollback_method>'
        }))
        sys.exit(1)

    backup_file = sys.argv[1]
    rollback_method = sys.argv[2]

    # Validate inputs
    if not os.path.isfile(backup_file):
        msg = 'Backup file does not exist: %s' % backup_file
        log_error(msg)
        print(json.dumps({'status': 'error', 'message': msg}))
        sys.exit(1)

    if not validate_backup_path(backup_file):
        msg = 'Backup file outside allowed directories: %s' % backup_file
        log_error(msg)
        print(json.dumps({'status': 'error', 'message': msg}))
        sys.exit(1)

    if rollback_method not in ('reboot', 'reload'):
        rollback_method = 'reboot'  # Default to safest option
        log_warning('Unknown rollback method, defaulting to reboot')

    # Prevent rollback during firmware updates
    if is_firmware_update_running():
        msg = 'Rollback blocked: firmware update in progress'
        log_warning(msg)
        print(json.dumps({'status': 'blocked', 'message': msg}))
        sys.exit(1)

    # Acquire exclusive lock
    lock_fd = acquire_restore_lock()
    if lock_fd is None:
        msg = 'Another rollback is already in progress'
        log_warning(msg)
        print(json.dumps({'status': 'locked', 'message': msg}))
        sys.exit(1)

    try:
        # Step 1: Restore config.xml
        log_info('=== ROLLBACK STARTING === backup=%s method=%s' % (
            backup_file, rollback_method))

        success, msg = restore_config(backup_file)
        if not success:
            print(json.dumps({'status': 'error', 'message': msg}))
            sys.exit(1)

        # Step 2: Apply the restored config
        if rollback_method == 'reboot':
            apply_success = apply_reboot()
        else:
            apply_success = apply_reload()

        if apply_success:
            log_info('=== ROLLBACK COMPLETE === method=%s' % rollback_method)
            print(json.dumps({
                'status': 'ok',
                'message': 'Rollback completed (method: %s)' % rollback_method,
                'backup_restored': backup_file,
                'method': rollback_method,
            }))
        else:
            log_error('=== ROLLBACK APPLY FAILED === method=%s' % rollback_method)
            # If reload failed, try reboot as last resort
            if rollback_method == 'reload':
                log_info('Reload failed, falling back to reboot')
                apply_reboot()
            print(json.dumps({
                'status': 'partial',
                'message': 'Config restored but service apply failed. Rebooting.',
                'backup_restored': backup_file,
            }))

    finally:
        release_restore_lock(lock_fd)


if __name__ == '__main__':
    main()
