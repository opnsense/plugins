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

#!/usr/bin/env python3

# Executed manually or via optional cron job to update the modules file

import requests
import json
import sys
from pathlib import Path

API_URL = "https://caddyserver.com/api/packages"
OUTPUT_FILE = Path("/usr/local/opnsense/mvc/app/models/OPNsense/XCaddy/xcaddy_build_modules.json")

def fetch_plugin_data():
    try:
        response = requests.get(API_URL, timeout=60)
        response.raise_for_status()
        return response.json().get("result", [])
    except requests.RequestException as e:
        print(f"Error fetching plugin data: {e}", file=sys.stderr)
        sys.exit(1)

def generate_plugin_map(plugins):
    plugin_map = {}
    for plugin in plugins:
        path = plugin.get("path")
        if path:
            # Use the last two segments of the path as the simplified identifier
            parts = path.split('/')
            if len(parts) >= 2:
                simplified = '/'.join(parts[-2:])
                plugin_map[path] = simplified
    return plugin_map

def save_plugin_map(plugin_map):
    try:
        OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)
        with OUTPUT_FILE.open("w") as f:
            json.dump(plugin_map, f, indent=4)
        print(f"Plugin map saved to {OUTPUT_FILE}")
    except IOError as e:
        print(f"Error saving plugin map: {e}", file=sys.stderr)
        sys.exit(1)

def main():
    plugins = fetch_plugin_data()
    plugin_map = generate_plugin_map(plugins)
    save_plugin_map(plugin_map)

if __name__ == "__main__":
    main()
