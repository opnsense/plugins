#!/usr/local/bin/python3

#
# Copyright (c) 2025 Cedrik Pischem
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification,
# are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.
#

import argparse
import subprocess
import sys
import fcntl
import os
import shutil
import json
from pathlib import Path
from shutil import which

LOCK_FILE = Path("/tmp/caddy_build.lock")
LOG_FILE = Path("/tmp/caddy_build.log")
FINAL_BINARY = Path("/usr/local/bin/caddy")
BUILD_OUTPUT = Path("/tmp/caddy")

def acquire_lock() -> int:
    '''
    Acquire a non-blocking exclusive file lock to prevent parallel builds.
    '''
    lock_fd = os.open(LOCK_FILE, os.O_CREAT | os.O_RDWR)
    try:
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        return lock_fd
    except BlockingIOError:
        with open(LOG_FILE, 'a') as log:
            log.write("ERROR: Another build is already running.\n")
        print("Another build is already running.", file=sys.stderr)
        os.close(lock_fd)
        sys.exit(1)

def release_lock(lock_fd: int) -> None:
    '''
    Release the file lock and close the lock file descriptor.
    '''
    fcntl.flock(lock_fd, fcntl.LOCK_UN)
    os.close(lock_fd)

def check_prerequisites() -> None:
    '''
    Ensure required tools (xcaddy and go) are available. Exit and log error if missing.
    '''
    missing = []
    if which("xcaddy") is None:
        missing.append("xcaddy")
    if which("go") is None:
        missing.append("go")
    if missing:
        with open(LOG_FILE, 'a') as log:
            log.write(f"ERROR: Missing required tool(s): {', '.join(missing)}\n")
        print(f"Missing required tool(s): {', '.join(missing)}", file=sys.stderr)
        sys.exit(1)

def parse_args():
    '''
    Parse command-line arguments for the Caddy version and optional module list.
    '''
    parser = argparse.ArgumentParser(description='Build custom Caddy binary using xcaddy.')
    parser.add_argument('--version', required=True, help='Caddy version to build')
    parser.add_argument('--modules', nargs='*', help='Space separated list of modules to include')
    return parser.parse_args()

def build_caddy(version: str, modules: list[str]) -> int:
    '''
    Run the xcaddy build command with the given version and modules.
    Log stdout and stderr to a file.
    Returns the xcaddy process exit code.
    '''
    # Clean up any previous build binary
    if BUILD_OUTPUT.exists():
        BUILD_OUTPUT.unlink()

    cmd = ['xcaddy', 'build', version, '--output', str(BUILD_OUTPUT)]
    for module in modules or []:
        cmd.extend(['--with', module])

    with open(LOG_FILE, 'w') as log:
        log.write(f"# xcaddy command: {' '.join(cmd)}\n\n")
        log.flush()

        process = subprocess.Popen(
            cmd,
            stdout=log,
            stderr=subprocess.STDOUT,
        )
        return process.wait()

def install_binary() -> None:
    '''
    Move the compiled Caddy binary to the final target path and make it executable.
    '''
    if not BUILD_OUTPUT.exists():
        raise FileNotFoundError("Compiled binary not found after build.")

    # Check if we can write to the destination directory
    if not os.access(FINAL_BINARY.parent, os.W_OK):
        raise PermissionError(f"Cannot write to {FINAL_BINARY.parent}. Run as root.")

    shutil.move(str(BUILD_OUTPUT), FINAL_BINARY)
    FINAL_BINARY.chmod(0o755)

def log_and_print(message: str, status: str = "info", log_path: Path = LOG_FILE) -> dict:
    '''
    Write message and status to the log file and console as json.
    '''
    data = {"status": status, "message": message}

    with open(log_path, 'a') as log:
        log.write(json.dumps(data) + '\n')

    return data

def main():
    '''
    Main execution flow:
    - Acquire build lock
    - Check prerequisites
    - Parse arguments
    - Build Caddy
    - Install binary if successful
    - Throw success or error
    - Release lock
    '''
    lock_fd = acquire_lock()
    try:
        check_prerequisites()
        args = parse_args()
        result = build_caddy(args.version, args.modules)

        if result == 0:
            try:
                install_binary()
                msg = log_and_print("Build and installation successful.", status="success")
                print(json.dumps(msg))
            except Exception as e:
                msg = log_and_print(f"Install failed: {e}", status="error")
                print(json.dumps(msg))
                sys.exit(1)
        else:
            msg = log_and_print(f"Build failed with exit code {result}.", status="error")
            print(json.dumps(msg))

        sys.exit(result)
    finally:
        release_lock(lock_fd)

if __name__ == '__main__':
    main()
