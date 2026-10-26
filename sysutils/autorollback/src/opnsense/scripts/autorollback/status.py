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
OPNsense Auto Rollback - Status Reporter

Returns the current state of the auto-rollback system as JSON.
Used by the dashboard widget, API, and CLI.

Usage: status.py (no arguments)
"""

import json
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib.common import (
    ensure_volatile_dir,
    read_model_settings,
    read_persistent_state, read_session_token,
    read_timer_pid, is_restore_in_progress,
)


def main():
    ensure_volatile_dir()

    now = time.time()
    settings = read_model_settings()
    state = read_persistent_state()

    # Safe mode
    safe_mode_active = False
    safe_mode_remaining = 0
    safe_mode_info = {}

    if state and state.get('mode') == 'safemode':
        expiry = state.get('expiry_time', 0)
        remaining = max(0, expiry - now)
        timer_pid = read_timer_pid()
        safe_mode_active = remaining > 0 or timer_pid is not None

        safe_mode_info = {
            'backup_file': state.get('backup_file', ''),
            'backup_revision': state.get('backup_revision', ''),
            'start_time': state.get('start_time', 0),
            'expiry_time': expiry,
            'remaining_seconds': int(remaining),
            'timeout': state.get('timeout', 0),
            'rollback_method': state.get('rollback_method', 'reboot'),
            'timer_pid': timer_pid,
        }
        safe_mode_remaining = int(remaining)

    # Overall state
    if is_restore_in_progress():
        system_state = 'restoring'
    elif safe_mode_active:
        system_state = 'safe_mode'
    elif settings['enabled']:
        system_state = 'armed'
    else:
        system_state = 'disabled'

    result = {
        'status': 'ok',
        'timestamp': now,
        'system_state': system_state,
        'settings': settings,
        'safe_mode': {
            'active': safe_mode_active,
            'remaining_seconds': safe_mode_remaining,
            **safe_mode_info,
        },
        'token': read_session_token(),
    }

    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(json.dumps({'status': 'error', 'message': str(e)}))
