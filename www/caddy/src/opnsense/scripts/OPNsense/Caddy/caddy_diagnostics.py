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


# Action handler
def perform_action(cmd_action):
    actions = {
        "config": show_caddy_config,
        "caddyfile": show_caddyfile
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
