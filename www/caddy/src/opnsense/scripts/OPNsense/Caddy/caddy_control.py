#!/usr/local/bin/python3

#
# Copyright (c) 2023-2024 Cedrik Pischem
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
import json
import sys

def run_service_command(action, action_message):
    result = {"message": action_message}

    if action == "validate":
        try:
            # Call Setup script
            subprocess.run(["/usr/local/opnsense/scripts/OPNsense/Caddy/setup.sh"], check=True)
            # Validate the Caddyfile with explicit --config flag, capturing both stdout and stderr
            validation_output = subprocess.check_output(["caddy", "validate", "--config", "/usr/local/etc/caddy/Caddyfile"], stderr=subprocess.STDOUT, text=True)
            if "Valid configuration" in validation_output:
                result["status"] = "ok"
                result["message"] = "Caddy configuration is valid."
            else:
                # Search for the specific error message
                error_msg = next((line for line in validation_output.split('\n') if line.startswith("Error:")), "Caddy configuration is not valid.")
                result["status"] = "failed"
                result["message"] = error_msg
        except subprocess.CalledProcessError as e:
            # Extracting only the specific "Error: ..." line from the output
            error_msg = next((line for line in e.output.split('\n') if line.startswith("Error:")), "Validation failed.")
            result["status"] = "failed"
            result["message"] = error_msg
    else:
        try:
            subprocess.run(["service", "caddy", action], check=True)
            result["status"] = "ok"
        except subprocess.CalledProcessError as e:
            result["status"] = "failed"
            result["message"] = str(e)

    return json.dumps(result)

# Updated actions dictionary
actions = {
    "start": "onestart",
    "stop": "onestop",
    "restart": "onerestart",
    "validate": "validate"  # Validate action
}

if __name__ == "__main__":
    action = sys.argv[1]  # Get the action from the command-line argument
    if action in actions:
        service_action = actions[action]
        message = f"{action.capitalize()}ing Caddy service" if action != "validate" else "Validating Caddy configuration"
        print(run_service_command(service_action, message))
    else:
        print(json.dumps({"status": "failed", "message": f"Unknown action: {action}"}))
