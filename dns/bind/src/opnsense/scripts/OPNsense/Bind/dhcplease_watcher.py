#!/usr/bin/env python3

"""
    Copyright (c) 2026 Bryan Wiegand <inbox@kw-ventures.com>
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

    ---------------------------------------------------------------------------
    Executable facade for the BIND DHCP lease watcher daemon.
"""

import argparse
import signal
import sys
import syslog

from dhcpwatcher import lease as lease_helpers
from dhcpwatcher.config import config_mtime, load_config
from dhcpwatcher.sources import IscDhcpSource, KeaDhcp4Source, KeaDhcp6Source
from dhcpwatcher.state import StateManager
from dhcpwatcher.updater import BindUpdater, run_nsupdate
from dhcpwatcher.watcher import (
    Watcher,
    set_runner_resolver,
    set_shutdown_checker,
)


shutdown_flag = False


def _run_nsupdate_from_facade(mapping, commands, zone=None):
    """Resolve the facade runner at call time for legacy monkey patches."""
    return run_nsupdate(mapping, commands, zone)


def handle_sigterm(signum, frame):
    """Gracefully stop the polling loop after SIGTERM."""
    global shutdown_flag
    shutdown_flag = True
    syslog.syslog(syslog.LOG_NOTICE, 'received SIGTERM, shutting down')


def _is_valid_hostname(hostname):
    return lease_helpers.is_valid_hostname(hostname)


def normalize_isc_lease(lease, suffix, source='isc-dhcp'):
    normalized = lease_helpers.normalize_isc_lease(lease, source)
    if normalized is None:
        return None
    return dict(normalized, state=lease.get('binding', 'active'), type=None)


def normalize_kea_lease(lease, source):
    normalized = lease_helpers.normalize_kea_lease(lease, source)
    if normalized is None:
        return None
    return dict(normalized, state=str(lease.get('state', 0)), type=lease.get('type'))


def build_fqdn(hostname, suffix):
    return lease_helpers.build_fqdn(hostname, suffix)


def build_forward_commands(action, address, fqdn):
    return lease_helpers.forward_commands(action, address, fqdn)


def build_reverse_commands(action, address, fqdn):
    return lease_helpers.reverse_commands(action, address, fqdn)


set_runner_resolver(_run_nsupdate_from_facade)
set_shutdown_checker(lambda: shutdown_flag)


def run_watcher():
    """Instantiate and run the watcher (used by Daemonize)."""
    Watcher().run()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--foreground', action='store_true', default=False,
        help='run in foreground (do not daemonize)',
    )
    parser.add_argument(
        '--pid', default='/var/run/bind_dhcplease.pid', help='pid file location'
    )
    args = parser.parse_args()

    syslog.openlog('bind-dhcplease', facility=syslog.LOG_LOCAL4)
    signal.signal(signal.SIGTERM, handle_sigterm)
    if args.foreground:
        run_watcher()
    else:
        syslog.syslog(syslog.LOG_NOTICE, 'daemonizing bind dhcpd watcher')
        sys.path.insert(0, '/usr/local/opnsense/site-python')
        from daemonize import Daemonize
        Daemonize(app='bind-dhcplease', pid=args.pid, action=run_watcher).start()
