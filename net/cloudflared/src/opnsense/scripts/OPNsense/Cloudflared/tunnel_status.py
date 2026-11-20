#!/usr/local/bin/python3

"""
Copyright (C) 2026 Alan Martines <alancpmartines@hotmail.com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
"""

import json
import os
import re
import urllib.error
import urllib.request

CONFIG_FILE = '/usr/local/etc/cloudflared/config.yml'
PID_FILE = '/var/run/cloudflared.pid'
# addresses cloudflared tries when no metrics address is configured
DEFAULT_METRICS_ADDRESSES = ['localhost:%d' % port for port in range(20241, 20246)]


def metrics_addresses():
    try:
        with open(CONFIG_FILE) as config:
            for line in config:
                match = re.match(r'^metrics:\s*"?([^"\s]+)"?\s*$', line)
                if match:
                    return [match.group(1)]
    except OSError:
        pass
    return DEFAULT_METRICS_ADDRESSES


def is_running():
    try:
        with open(PID_FILE) as pidfile:
            os.kill(int(pidfile.read().split()[0]), 0)
        return True
    except (OSError, ValueError, IndexError):
        return False


def ready_status(address):
    # /ready answers 200 with active edge connections and 503 without
    try:
        with urllib.request.urlopen('http://%s/ready' % address, timeout=3) as response:
            return json.load(response)
    except urllib.error.HTTPError as error:
        try:
            return json.load(error)
        except ValueError:
            return None
    except (OSError, ValueError):
        return None


if __name__ == '__main__':
    result = {'status': 'stopped', 'connections': 0}
    if is_running():
        result['status'] = 'disconnected'
        for address in metrics_addresses():
            ready = ready_status(address)
            if isinstance(ready, dict) and 'readyConnections' in ready:
                result['connections'] = int(ready['readyConnections'])
                if result['connections'] > 0:
                    result['status'] = 'connected'
                break
    print(json.dumps(result))
