#!/usr/local/bin/python3

"""
    Copyright (C) 2026 VEQNORA
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

    account expiry job: users listed in users.meta with an expiration date
    in the past are removed from mpd.secret (mpd5 re-reads the file on
    every authentication, so no restart is needed) and their active
    sessions are disconnected.  designed to run daily from cron and from
    the rc.d start_precmd.
"""

import argparse
import datetime
import importlib.util
import json
import os
import re
import sys
import tempfile

CONF_DIR = '/usr/local/etc/pppoe_server'
META_FILE = os.path.join(CONF_DIR, 'users.meta')
SECRET_FILE = os.path.join(CONF_DIR, 'mpd.secret')
DATE_RE = re.compile(r'^\d{4}-\d{2}-\d{2}$')

_spec = importlib.util.spec_from_file_location(
    'pppoe_console', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'pppoe_console.py'))
pppoe_console = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pppoe_console)


def expired_usernames(meta_path=META_FILE, today=None):
    today = today or datetime.date.today()
    expired = []
    try:
        with open(meta_path, 'r', encoding='utf-8') as handle:
            for line in handle:
                parts = line.rstrip('\n').split('\t')
                if len(parts) != 2:
                    continue
                username, expires = parts
                if not pppoe_console.USERNAME_RE.match(username) or not DATE_RE.match(expires):
                    continue
                try:
                    expiry = datetime.date.fromisoformat(expires)
                except ValueError:
                    continue
                if expiry <= today:
                    expired.append(username)
    except OSError:
        pass
    return expired


def prune_secret(usernames, secret_path=SECRET_FILE):
    """remove secret lines of the given users; atomic replace, 0600 kept"""
    if not usernames:
        return 0
    try:
        with open(secret_path, 'r', encoding='utf-8') as handle:
            lines = handle.readlines()
    except OSError:
        return 0
    blocked = set(usernames)
    kept, removed = [], 0
    for line in lines:
        name = line.split(' ', 1)[0].strip()
        if name in blocked:
            removed += 1
            continue
        kept.append(line)
    if removed:
        fd, tmp_path = tempfile.mkstemp(dir=os.path.dirname(secret_path))
        try:
            with os.fdopen(fd, 'w', encoding='utf-8') as handle:
                handle.writelines(kept)
            os.chmod(tmp_path, 0o600)
            os.replace(tmp_path, secret_path)
        except OSError:
            os.unlink(tmp_path)
            raise
    return removed


def disconnect_users(usernames):
    disconnected = 0
    try:
        host, port, username, password = pppoe_console.read_auth()
        client = pppoe_console.ConsoleClient(host, port, username, password)
        try:
            sessions = pppoe_console.parse_sessions(client.run('show sessions'))
            for session in sessions:
                if session['username'] in usernames:
                    client.run('session %s' % session['session_id'])
                    client.run('close')
                    disconnected += 1
        finally:
            client.close()
    except (OSError, ValueError, ConnectionError):
        pass  # service not running - nothing to disconnect
    return disconnected


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--prune-only', action='store_true',
                        help='only rewrite mpd.secret, do not touch sessions')
    args = parser.parse_args()

    expired = expired_usernames()
    removed = prune_secret(expired)
    disconnected = 0 if args.prune_only else disconnect_users(set(expired))
    print(json.dumps({
        'status': 'ok',
        'expired': len(expired),
        'secrets_removed': removed,
        'sessions_disconnected': disconnected,
    }))
    return 0


if __name__ == '__main__':
    sys.exit(main())
