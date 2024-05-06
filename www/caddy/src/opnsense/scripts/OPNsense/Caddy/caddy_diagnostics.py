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

        # If the file is valid JSON, return it as raw string
        return config_data
    except FileNotFoundError:
        sys.exit("Caddy autosave configuration file not found.")
    except json.JSONDecodeError:
        sys.exit("Error decoding the Caddy configuration JSON. The file is not valid JSON.")
    except Exception as e:
        sys.exit(f"An error occurred: {str(e)}")

def perform_action(action):
    actions = {
        "showconfig": show_caddy_config
        # Additional actions can be added here in the same format.
    }

    action_func = actions.get(action)
    if action_func:
        return action_func()
    else:
        return f"Unknown action: {action}"

if __name__ == "__main__":
    if len(sys.argv) > 1:
        action = sys.argv[1]
        result = perform_action(action)
        if result:
            print(result)
    else:
        print("No action specified.")

