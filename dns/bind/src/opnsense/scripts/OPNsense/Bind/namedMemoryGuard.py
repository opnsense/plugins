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


"""Protect firewall memory while BIND loads a DNSBL response-policy zone."""

import ctypes
import os
import re
import signal
import subprocess
import sys
import time


ENV = os.environ
DNSBL_FILE = ENV.get("NAMED_GUARD_DNSBL_FILE", "/usr/local/etc/namedb/dnsbl.inc")
RC_CONF = ENV.get("NAMED_GUARD_RC_CONF", "/etc/rc.conf.d/named")
DEFAULT_MIN_FREE_MB = ENV.get("NAMED_GUARD_DEFAULT_MIN_FREE_MB", "300")
TIMEOUT_SECONDS = ENV.get("NAMED_GUARD_TIMEOUT_SECONDS", "90")
SAMPLE_SECONDS = ENV.get("NAMED_GUARD_SAMPLE_SECONDS", "0.1")
LOGGER = ENV.get("NAMED_GUARD_LOGGER", "logger")
PS = ENV.get("NAMED_GUARD_PS", "ps")
RECOVER = ENV.get(
    "NAMED_GUARD_RECOVER",
    "/usr/local/opnsense/scripts/OPNsense/Bind/dnsblMemoryRecovery.sh",
)
SYSCTL = ENV.get("NAMED_GUARD_SYSCTL", "sysctl")
STATUS = ENV.get(
    "NAMED_GUARD_STATUS",
    "/usr/local/opnsense/scripts/OPNsense/Bind/dnsblStatus.py",
)
FREE_COUNT = "vm.stats.vm.v_free_count"
FREE_TARGET = "vm.stats.vm.v_free_target"


def command_output(*command):
    try:
        result = subprocess.run(command, capture_output=True, text=True, check=False)
    except OSError:
        return None
    return result.stdout.strip() if result.returncode == 0 else None


def command(*args):
    try:
        return subprocess.run(args, check=False).returncode
    except OSError:
        return 1


def read_vm_counter(name):
    try:
        value = ctypes.c_ulong()
        value_size = ctypes.c_size_t(ctypes.sizeof(value))
        result = ctypes.CDLL(None).sysctlbyname(
            name.encode(),
            ctypes.byref(value),
            ctypes.byref(value_size),
            None,
            0,
        )
        if result == 0:
            return value.value
    except (AttributeError, OSError):
        pass

    value = command_output("sysctl", "-n", name)
    try:
        return int(value) if value is not None else None
    except ValueError:
        return None


def page_size_kb():
    try:
        return os.sysconf("SC_PAGE_SIZE") // 1024
    except (AttributeError, ValueError, OSError):
        return None


def process_running(pid):
    try:
        os.kill(int(pid), 0)
    except (ProcessLookupError, ValueError):
        return False
    except PermissionError:
        return True
    return True


def read_rc_conf():
    try:
        with open(RC_CONF, encoding="utf-8") as handle:
            return handle.read()
    except OSError:
        return ""


def dnsbl_enabled():
    return ENV.get("NAMED_GUARD_ENABLED") == "1" or bool(
        re.search(r'^named_dnsbl="[^"]', read_rc_conf(), re.MULTILINE)
    )


def minimum_free_kb():
    configured = ENV.get("NAMED_GUARD_MIN_FREE_KB")
    if configured is not None:
        configured_kb = int(configured) if configured.isdigit() else None
    else:
        match = re.search(r'^named_memory_guard_mb="([0-9]+)"$', read_rc_conf(), re.MULTILINE)
        memory_guard_mb = match.group(1) if match else DEFAULT_MIN_FREE_MB
        configured_kb = int(memory_guard_mb) * 1024 if memory_guard_mb.isdigit() else None

    if configured_kb is None or configured_kb == 0:
        return configured_kb

    target_pages = read_vm_counter(FREE_TARGET)
    page_kb = page_size_kb()
    if target_pages is None or page_kb is None:
        return None
    return configured_kb + (target_pages * page_kb)


def stop_named(pid, free_kb, minimum_kb, selected_codes):
    rss_kb = command_output(PS, "-o", "rss=", "-p", pid) or "unknown"
    command(
        STATUS,
        "guard_recovered",
        "Memory Guard stopped DNSBL/RPZ loading and is restarting BIND without DNSBL.",
    )
    command(
        LOGGER,
        "-p",
        "daemon.crit",
        "-t",
        "named",
        "DNSBL startup memory guard stopped named "
        f"(pid {pid}): {free_kb} KiB free, below the {minimum_kb} KiB minimum; "
        f"RSS {rss_kb} KiB.",
    )
    try:
        os.kill(int(pid), signal.SIGTERM)
    except (ProcessLookupError, ValueError, PermissionError):
        pass
    time.sleep(1)
    if process_running(pid):
        try:
            os.kill(int(pid), signal.SIGKILL)
        except (ProcessLookupError, ValueError, PermissionError):
            pass
    command(RECOVER, selected_codes)


def main():
    if not os.path.isfile(DNSBL_FILE) or os.path.getsize(DNSBL_FILE) == 0 or not dnsbl_enabled():
        command(STATUS, "disabled", "DNSBL/RPZ is disabled.")
        return 0

    minimum_kb = minimum_free_kb()
    if minimum_kb is None or minimum_kb == 0:
        return 0

    page_kb = page_size_kb()
    try:
        timeout_seconds = float(TIMEOUT_SECONDS)
        sample_seconds = max(0.1, float(SAMPLE_SECONDS))
    except ValueError:
        return 0
    if page_kb is None:
        return 0

    expected_pid = sys.argv[1] if len(sys.argv) > 1 else ""
    selected_codes = sys.argv[2] if len(sys.argv) > 2 else ""
    if not expected_pid or not selected_codes:
        return 0

    deadline = time.monotonic() + timeout_seconds
    while time.monotonic() < deadline:
        if not process_running(expected_pid):
            return 0

        free_pages = read_vm_counter(FREE_COUNT)
        if free_pages is None:
            break
        free_kb = free_pages * page_kb
        if free_kb < minimum_kb:
            stop_named(expected_pid, free_kb, minimum_kb, selected_codes)
            return 1
        time.sleep(sample_seconds)

    command(STATUS, "dnsbl_active", "BIND loaded DNSBL/RPZ successfully.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
