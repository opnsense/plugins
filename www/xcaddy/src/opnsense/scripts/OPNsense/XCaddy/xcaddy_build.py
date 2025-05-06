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

import subprocess
import sys
import fcntl
import os
import shutil
import json
from pathlib import Path
from shutil import which

CONFIG_FILE = Path("/usr/local/etc/xcaddy/xcaddy_build_config.json")
LOCK_FILE = Path("/usr/local/etc/xcaddy/xcaddy_build.lock")
STATUS_FILE = Path("/usr/local/etc/xcaddy/xcaddy_build.status")
BUILD_OUTPUT = Path("/usr/local/etc/xcaddy/caddy")
FINAL_BINARY = Path("/usr/local/bin/caddy")
LOG_FILE = Path("/var/log/xcaddy/caddy_build.log")
PID_FILE = Path("/var/run/caddy/caddy.pid")

def ensure_directories_exist() -> None:
    '''Ensure all parent directories for required paths exist.'''
    paths = [
        CONFIG_FILE,
        LOCK_FILE,
        STATUS_FILE,
        BUILD_OUTPUT,
        LOG_FILE,
        PID_FILE
    ]
    for path in paths:
        path.parent.mkdir(parents=True, exist_ok=True)


def log_message(level: str, message: str) -> None:
    '''Append a message to the log file with the given level (e.g., ERROR, WARNING, INFO).'''
    with open(LOG_FILE, 'a') as log:
        log.write(f"{level}: {message}\n")


def exit_with_error(message: str, code: int = 1) -> None:
    '''Log an error, print it to stderr, and exit with a given code.'''
    log_message("ERROR", message)
    print(message, file=sys.stderr)
    sys.exit(code)


def acquire_lock() -> int:
    '''Acquire a non-blocking exclusive file lock to prevent parallel builds.'''
    lock_fd = os.open(LOCK_FILE, os.O_CREAT | os.O_RDWR)
    try:
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        return lock_fd
    except BlockingIOError:
        exit_with_error("Another build is already running.")


def release_lock(lock_fd: int) -> None:
    '''Release the file lock and close the lock file descriptor.'''
    fcntl.flock(lock_fd, fcntl.LOCK_UN)
    os.close(lock_fd)


def check_prerequisites() -> None:
    '''Ensure required tools (xcaddy and go) are available. Exit and log error if missing.'''
    missing = []
    if which("xcaddy") is None:
        missing.append("xcaddy")
    if which("go") is None:
        missing.append("go")
    if missing:
        exit_with_error(f"Missing required tool(s): {', '.join(missing)}")


def load_build_config() -> tuple[str, list[str]]:
    '''
    Load Caddy build configuration including version, default modules, and user modules.
    Returns a tuple of version and a full combined module list.
    '''
    if not CONFIG_FILE.exists():
        raise FileNotFoundError(f"Config file not found: {CONFIG_FILE}")
    with CONFIG_FILE.open("r") as f:
        config = json.load(f)
        version = config.get("version", "")
        default_modules = config.get("default_modules", [])
        user_modules = config.get("user_modules", [])
        all_modules = sorted(set(default_modules + user_modules))
        return version, all_modules


def build_caddy(version: str, modules: list[str]) -> int:
    '''Run the xcaddy build command with the given version and modules.'''
    if BUILD_OUTPUT.exists():
        BUILD_OUTPUT.unlink()

    cmd = ['xcaddy', 'build', version, '--output', str(BUILD_OUTPUT)]
    for module in modules:
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
    '''Move the compiled Caddy binary to the final target path and make it executable.'''
    if not BUILD_OUTPUT.exists():
        raise FileNotFoundError("Compiled binary not found after build.")

    if not os.access(FINAL_BINARY.parent, os.W_OK):
        raise PermissionError(f"Cannot write to {FINAL_BINARY.parent}. Run as root.")

    shutil.move(str(BUILD_OUTPUT), FINAL_BINARY)
    FINAL_BINARY.chmod(0o755)


def write_status(status: str, message: str) -> None:
    '''Write a single-line JSON status update to the status file.'''
    data = {"status": status, "message": message}
    STATUS_FILE.write_text(json.dumps(data) + "\n")


def detach_to_background():
    '''If not already forked, fork the script into the background and exit parent.'''
    if os.getenv("CADDY_BUILD_FORKED") != "1":
        env = os.environ.copy()
        env["CADDY_BUILD_FORKED"] = "1"
        subprocess.Popen(
            [sys.executable] + sys.argv,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            env=env,
            start_new_session=True
        )
        sys.exit(0)


def replace_binary_safely() -> None:
    '''Stop Caddy if running, replace the binary, and start it again if it was running.'''
    was_running = PID_FILE.exists()

    if was_running:
        try:
            subprocess.run(
                ["/usr/local/sbin/configctl", "caddy", "stop"],
                check=True,
                capture_output=True,
                text=True
            )
            log_message("INFO", "Caddy stopped successfully before binary replacement.")
        except subprocess.CalledProcessError as e:
            log_message("WARNING", f"Failed to stop Caddy: {e}")
            log_message("STDOUT", e.stdout)
            log_message("STDERR", e.stderr)

    try:
        install_binary()
    except Exception as e:
        if was_running:
            log_message("INFO", "Attempting to restart Caddy after failed install.")
            subprocess.run(["/usr/local/sbin/configctl", "caddy", "start"])
        raise

    if was_running:
        try:
            subprocess.run(
                ["/usr/local/sbin/configctl", "caddy", "start"],
                check=True,
                capture_output=True,
                text=True
            )
            log_message("INFO", "Caddy started successfully after binary replacement.")
        except subprocess.CalledProcessError as e:
            log_message("WARNING", f"Failed to start Caddy: {e}")
            log_message("STDOUT", e.stdout)
            log_message("STDERR", e.stderr)


def main():
    detach_to_background()
    ensure_directories_exist()
    lock_fd = acquire_lock()
    write_status("running", "Build in progress. Please be patient, this might take a few minutes.")

    try:
        check_prerequisites()
        version, all_modules = load_build_config()
        result = build_caddy(version, all_modules)

        if result != 0:
            write_status("error", f"Build failed with exit code {result}. Check {LOG_FILE}. Binary was not replaced.")
            sys.exit(result)

        replace_binary_safely()
        write_status("success", "Build successful.")
        sys.exit(0)

    except Exception as e:
        write_status("error", f"Build process failed: {e}")
        log_message("ERROR", f"Unhandled exception: {e}")
        sys.exit(1)

    finally:
        release_lock(lock_fd)

if __name__ == '__main__':
    main()
