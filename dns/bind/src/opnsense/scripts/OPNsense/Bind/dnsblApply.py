#!/usr/local/bin/python3
# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


"""Fetch DNSBL data, restart BIND, and report the startup outcome."""

import atexit
import os
import signal
import subprocess
import sys
import time
from pathlib import Path


ENV = os.environ
DNSBL_SCRIPT = ENV.get(
    "DNSBL_APPLY_DNSBL_SCRIPT",
    "/usr/local/opnsense/scripts/OPNsense/Bind/dnsbl.py",
)
STATUS_SCRIPT = ENV.get(
    "DNSBL_APPLY_STATUS_SCRIPT",
    "/usr/local/opnsense/scripts/OPNsense/Bind/dnsblStatus.py",
)
PLUGINCTL = ENV.get("DNSBL_APPLY_PLUGINCTL", "/usr/local/sbin/pluginctl")
LOCK_DIR = Path(ENV.get("DNSBL_APPLY_LOCK_DIR", "/var/run/bind/dnsbl-apply.lock"))
LOGGER = ENV.get("DNSBL_APPLY_LOGGER", "logger")
try:
    TERMINAL_WAIT_SECONDS = int(ENV.get("DNSBL_APPLY_TERMINAL_WAIT_SECONDS", "120"))
except ValueError:
    TERMINAL_WAIT_SECONDS = 120


def run(*command, capture_output=False):
    try:
        return subprocess.run(command, check=False, text=True, capture_output=capture_output)
    except OSError:
        return None


def status(*arguments):
    return run(STATUS_SCRIPT, *arguments)


def release_lock():
    try:
        (LOCK_DIR / "pid").unlink()
    except FileNotFoundError:
        pass
    try:
        LOCK_DIR.rmdir()
    except FileNotFoundError:
        pass
    except OSError:
        pass


def interrupted(_signal, _frame):
    raise SystemExit(1)


def acquire_lock():
    try:
        LOCK_DIR.mkdir()
    except FileExistsError:
        run(
            LOGGER,
            "-p",
            "daemon.notice",
            "-t",
            "named",
            "DNSBL apply ignored because another DNSBL operation is already running.",
        )
        return False
    try:
        (LOCK_DIR / "pid").write_text(f"{os.getpid()}\n", encoding="utf-8")
    except OSError:
        release_lock()
        return False
    return True


def stage():
    result = run(STATUS_SCRIPT, "--stage", capture_output=True)
    return result.stdout.strip() if result and result.returncode == 0 else ""


def main():
    if not acquire_lock():
        return 0
    atexit.register(release_lock)
    for signal_name in (signal.SIGHUP, signal.SIGINT, signal.SIGTERM):
        signal.signal(signal_name, interrupted)

    result = run(DNSBL_SCRIPT, *sys.argv[1:])
    if result is None or result.returncode != 0:
        return result.returncode if result else 1

    status("starting", "BIND is loading DNSBL/RPZ; monitoring Memory Guard.")
    result = run(PLUGINCTL, "-c", "dns")
    if result is None or result.returncode != 0:
        status("failed", "BIND could not restart after DNSBL download.")
        return 1

    for _ in range(max(0, TERMINAL_WAIT_SECONDS)):
        if stage() in {"dnsbl_active", "guard_recovered", "disabled", "failed"}:
            return 0
        time.sleep(1)
    status("failed", "DNSBL startup monitoring did not reach a terminal state.")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
