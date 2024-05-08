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

def show_caddy_config():
    config_path = "/var/db/caddy/config/caddy/autosave.json"

    try:
        # Open and read the JSON configuration file
        with open(config_path, "r") as file:
            config_data = file.read()  # Read the file as raw text

        # Attempt to decode the JSON to validate its integrity
        json.loads(config_data)  # This line is just to validate JSON

        # Print the valid JSON to stdout
        print(config_data)
    except FileNotFoundError:
        # Output error details in JSON format so that the API can consume them
        print(json.dumps({"error": "File not found", "message": "Caddy autosave.json configuration file not found"}))
    except json.JSONDecodeError:
        print(json.dumps({"error": "Invalid JSON", "message": "Error decoding the Caddy autosave.json, the file is not valid JSON"}))
    except Exception as e:
        print(json.dumps({"error": "General Error", "message": str(e)}))

def perform_action(action):
    actions = {
        "config": show_caddy_config
        # Additional actions can be added here in the same format.
    }

    action_func = actions.get(action)
    if action_func:
        action_func()
    else:
        # Output error details in JSON format if action is unknown
        print(json.dumps({"error": "Unknown Action", "message": f"Unknown action: {action}"}))

if __name__ == "__main__":
    if len(sys.argv) > 1:
        action = sys.argv[1]
        perform_action(action)
    else:
        # Output error details in JSON format if no action is specified
        print(json.dumps({"error": "No Action Specified", "message": "No action specified"}))

