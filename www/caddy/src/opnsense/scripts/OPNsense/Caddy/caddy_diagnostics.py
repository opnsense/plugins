#!/usr/local/bin/python3

#
# Copyright (c) 2024 Cedrik Pischem
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

import sys
import json
import os
import subprocess
import asyncio
from datetime import datetime


# Function to show the Caddy configuration from a JSON file
def show_caddy_config():
    config_path = "/var/db/caddy/config/caddy/autosave.json"

    try:
        # Open and read the JSON configuration file directly into a Python dictionary
        with open(config_path, "r") as file:
            config_data = json.load(file)  # Load the JSON directly

        # Print the JSON to stdout using json.dumps to ensure it's a JSON string and nicely formatted
        print(json.dumps(config_data))  # Output the JSON directly
        # Output error details in JSON format so that the API can consume them
    except FileNotFoundError:
        print(json.dumps({"error": "File not found", "message": "Caddy autosave.json configuration file not found"}))
    except json.JSONDecodeError:
        print(json.dumps(
            {"error": "Invalid JSON", "message": "Error decoding the Caddy autosave.json, the file is not valid JSON"}))
    except Exception as e:
        print(json.dumps({"error": "General Error", "message": str(e)}))


def show_caddyfile():
    caddyfile_path = "/usr/local/etc/caddy/Caddyfile"

    try:
        with open(caddyfile_path, "r") as file:
            caddyfile_data = file.read()
        # Output the Caddyfile data directly as a JSON object with a generic key like "content"
        print(json.dumps({"content": caddyfile_data}))
    except FileNotFoundError:
        print(json.dumps({"error": "Caddyfile not found", "message": "Caddyfile not found"}))
    except Exception as e:
        print(json.dumps({"error": "General Error", "message": str(e)}))


# Function to extract certificate information using openssl command
async def extract_certificate_info(cert_path):
    try:
        # Execute the openssl command to get the expiration date with a timeout
        result = await asyncio.wait_for(
            asyncio.create_subprocess_exec(
                'openssl', 'x509', '-in', cert_path, '-noout', '-enddate',
                stdout=subprocess.PIPE, stderr=subprocess.PIPE),
            timeout=10)  # Make sure tasks are cleaned up if they hang

        stdout, stderr = await result.communicate()

        # Check for errors in the execution
        if result.returncode != 0:
            error_message = stderr.decode().strip()
            raise RuntimeError(f"Subprocess failed with error: {error_message}")

        # Decode output and process the information
        expiration_date_str = stdout.decode().strip().split('=')[1]

        # Convert expiration date string to datetime object
        expiration_date = datetime.strptime(expiration_date_str, "%b %d %H:%M:%S %Y GMT")

        # Determine the current date
        now = datetime.now()

        # Calculate remaining days until expiration
        remaining_days = (expiration_date - now).days
        remaining_days = max(remaining_days, 0)  # Ensure non-negative days

        # Extract the hostname from the filename
        hostname = os.path.basename(cert_path).replace('.crt', '').lower()

        return {'hostname': hostname, 'expiration_date': expiration_date_str, 'remaining_days': remaining_days}
    except asyncio.TimeoutError as e:
        # Handle timeout specific errors
        raise RuntimeError(f"Timeout occurred while processing {cert_path}: {str(e)}")
    except Exception as e:
        raise RuntimeError(f"Error extracting certificate info for {cert_path}: {str(e)}")


# Function to find certificates and create tasks to extract info
async def find_certificates(base_dir):
    tasks = []
    for root, dirs, files in os.walk(base_dir):
        # Skip any directories named 'temp'
        dirs[:] = [d for d in dirs if d != 'temp']
        for file in files:
            if file.endswith('.crt'):
                cert_path = os.path.join(root, file)
                task = asyncio.create_task(extract_certificate_info(cert_path))
                tasks.append(task)

    if not tasks:
        raise RuntimeError("No certificates were found in the specified directory.")

    results = await asyncio.gather(*tasks, return_exceptions=True)
    return [result for result in results if not isinstance(result, Exception)]


# Function to show certificates, processing all found in the given directory
async def show_certificates():
    # Function to show certificates, processing all found in the given directory
    base_dir = '/var/db/caddy/data/caddy/certificates'
    try:
        certificates_data = await find_certificates(base_dir)
        if certificates_data:
            print(json.dumps(certificates_data))
        else:
            raise RuntimeError("No valid certificate data found.")
    except Exception as e:
        print(json.dumps({"error": "General Error", "message": str(e)}))


# Action handler
def perform_action(cmd_action):
    actions = {
        "config": show_caddy_config,
        "caddyfile": show_caddyfile,
        "certificate": lambda: asyncio.run(show_certificates())
    }

    action_func = actions.get(cmd_action)
    if action_func:
        action_func()
    else:
        # Output error details in JSON format if action is unknown
        print(json.dumps({"error": "Unknown Action", "message": f"Unknown action: {cmd_action}"}))


if __name__ == "__main__":
    if len(sys.argv) > 1:
        perform_action(sys.argv[1])
    else:
        # Output error details in JSON format if no action is specified
        print(json.dumps({"error": "No Action Specified", "message": "No action specified"}))
