#!/usr/local/bin/python3
"""
logs.py - Returns LightScope logs as JSON
"""

import json
import os
import subprocess

LOG_FILE = "/var/log/lightscope.log"
MAX_LINES = 100

def get_logs():
    """Get recent LightScope logs."""
    logs = ""

    # Try reading from log file first
    if os.path.exists(LOG_FILE):
        try:
            with open(LOG_FILE, 'r') as f:
                lines = f.readlines()
                logs = ''.join(lines[-MAX_LINES:])
        except Exception as e:
            logs = f"Error reading log file: {e}"

    # If no log file, try syslog
    if not logs:
        try:
            proc = subprocess.run(
                ['grep', '-i', 'lightscope', '/var/log/system.log'],
                capture_output=True,
                text=True,
                timeout=5
            )
            if proc.stdout:
                lines = proc.stdout.strip().split('\n')
                logs = '\n'.join(lines[-MAX_LINES:])
            else:
                logs = "No logs found. Service may not have been started yet."
        except Exception as e:
            logs = f"Error reading syslog: {e}"

    return {"logs": logs}

if __name__ == "__main__":
    print(json.dumps(get_logs()))
