#!/usr/local/bin/python3
"""
status.py - Returns LightScope status as JSON for API/widget
"""

import json
import os
import re
import socket
import configparser
import subprocess

CONFIG_FILE = "/usr/local/etc/lightscope.conf"
DASHBOARD_URL = "https://thelightscope.com/light_table"


def get_firewall_allowed_ports():
    """
    Get list of ports that have PASS/ALLOW rules in the firewall.
    These are ports where legitimate services may be running.
    Returns a set of individual ports AND a list of (start, end) tuples for ranges.
    """
    allowed_ports = set()
    allowed_ranges = []

    try:
        # Get active pf rules
        result = subprocess.run(
            ["pfctl", "-sr"],
            capture_output=True,
            text=True,
            timeout=5
        )

        if result.returncode == 0:
            for line in result.stdout.split('\n'):
                # Look for pass rules with port specifications
                # Example: pass in on em0 proto tcp from any to any port = 22
                # Example: pass in on em0 proto tcp from any to any port 1:4343
                if line.startswith('pass') and 'proto tcp' in line:
                    # Match port ranges like "port 1:4343" or "port 80:443"
                    range_match = re.search(r'port\s*[=]?\s*(\d+):(\d+)', line)
                    if range_match:
                        start_port = int(range_match.group(1))
                        end_port = int(range_match.group(2))
                        allowed_ranges.append((start_port, end_port))
                        continue  # Don't also match as single port

                    # Match single port patterns like "port = 22" or "port 22"
                    port_match = re.search(r'port\s*[=]?\s*(\d+)(?![\d:])', line)
                    if port_match:
                        allowed_ports.add(int(port_match.group(1)))

                    # Match port lists in braces like "port { 22 80 443 }"
                    brace_match = re.search(r'port\s*[=]?\s*\{([^}]+)\}', line)
                    if brace_match:
                        ports_str = brace_match.group(1)
                        for p in re.findall(r'\d+', ports_str):
                            allowed_ports.add(int(p))
    except Exception as e:
        pass

    return allowed_ports, allowed_ranges


def is_port_allowed_by_firewall(port, allowed_ports, allowed_ranges):
    """Check if a port is allowed by firewall rules (including ranges)."""
    if port in allowed_ports:
        return True
    for start, end in allowed_ranges:
        if start <= port <= end:
            return True
    return False


def get_lightscope_pids():
    """Get all lightscope-related PIDs (main daemon and child processes)."""
    pids = set()
    try:
        # Get all processes matching lightscope_daemon
        result = subprocess.run(
            ["pgrep", "-f", "lightscope_daemon"],
            capture_output=True,
            text=True,
            timeout=5
        )
        if result.returncode == 0:
            for line in result.stdout.strip().split('\n'):
                if line.strip():
                    try:
                        pids.add(int(line.strip()))
                    except ValueError:
                        pass
    except Exception:
        pass
    return pids


def is_port_in_use_by_other(port, lightscope_pids=None):
    """
    Check if a port is in use by a service OTHER than lightscope.
    Returns True if another service is using it, False if available or used by lightscope.
    """
    if lightscope_pids is None:
        lightscope_pids = set()

    try:
        # Use sockstat to see what's listening on the port
        result = subprocess.run(
            ["sockstat", "-4", "-l", "-p", str(port)],
            capture_output=True,
            text=True,
            timeout=5
        )

        if result.returncode == 0:
            lines = result.stdout.strip().split('\n')
            # Skip header line, check remaining lines
            for line in lines[1:]:
                if line.strip():
                    # sockstat output: USER COMMAND PID FD PROTO LOCAL FOREIGN
                    parts = line.split()
                    if len(parts) >= 3:
                        try:
                            sock_pid = int(parts[2])
                            # Check if this is one of our lightscope processes
                            if sock_pid in lightscope_pids:
                                return False  # It's our honeypot, not a conflict
                        except ValueError:
                            pass
                    # Something else is using the port
                    return True

        # No one listening - port is available
        return False

    except Exception:
        # Fall back to socket bind test
        try:
            test_sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            test_sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            test_sock.settimeout(0.5)
            test_sock.bind(("", port))
            test_sock.close()
            return False
        except OSError:
            return True


def get_port_status(ports_string):
    """
    Check status of honeypot ports against firewall rules and port usage.
    Returns dict with port status info.
    """
    port_status = {}

    if not ports_string:
        return port_status

    allowed_ports, allowed_ranges = get_firewall_allowed_ports()
    lightscope_pids = get_lightscope_pids()

    for p in ports_string.split(','):
        p = p.strip()
        if p.isdigit():
            port = int(p)
            if 1 <= port <= 65535:
                if is_port_allowed_by_firewall(port, allowed_ports, allowed_ranges):
                    # Port has a firewall ALLOW rule - potential conflict
                    port_status[port] = "firewall_conflict"
                elif is_port_in_use_by_other(port, lightscope_pids):
                    # Port is already in use by another service (not lightscope)
                    port_status[port] = "in_use"
                else:
                    # Port is safe for honeypot or already opened by lightscope
                    port_status[port] = "ok"

    return port_status


def get_status():
    """Get LightScope status information."""
    result = {
        "status": "stopped",
        "database": "",
        "dashboard_url": "",
        "honeypot_ports": "",
        "port_status": {},
        "config_exists": False
    }

    # Check if config exists
    if os.path.exists(CONFIG_FILE):
        result["config_exists"] = True
        try:
            config = configparser.ConfigParser()
            config.read(CONFIG_FILE)

            database = config.get('Settings', 'database', fallback='').strip()
            if database:
                result["database"] = database
                result["dashboard_url"] = f"{DASHBOARD_URL}/{database}"

            result["honeypot_ports"] = config.get('Settings', 'honeypot_ports', fallback='')
        except Exception as e:
            result["config_error"] = str(e)

    # Check if service is running via pid file
    pidfile = "/var/run/lightscope.pid"
    try:
        if os.path.exists(pidfile):
            with open(pidfile, 'r') as f:
                pid = int(f.read().strip())
            # Check if process is actually running
            os.kill(pid, 0)  # Signal 0 just checks if process exists
            result["status"] = "running"
        else:
            result["status"] = "stopped"
    except (ProcessLookupError, ValueError, PermissionError):
        result["status"] = "stopped"
    except Exception:
        result["status"] = "unknown"

    # Check process count
    try:
        proc = subprocess.run(
            ["pgrep", "-f", "lightscope_daemon"],
            capture_output=True,
            text=True,
            timeout=5
        )
        pids = proc.stdout.strip().split('\n')
        result["process_count"] = len([p for p in pids if p])
    except Exception:
        result["process_count"] = 0

    # Check port status against firewall rules
    result["port_status"] = get_port_status(result["honeypot_ports"])

    return result

if __name__ == "__main__":
    print(json.dumps(get_status()))
