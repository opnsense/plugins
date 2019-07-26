"""
    Copyright (c) 2018-2019 Ad Schellevis <ad@opnsense.org>
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
import os
import time
import subprocess
import ujson
from . import telemetry_sids

class Stats:
    def __init__(self):
        self._our_sids = telemetry_sids()

    def software_version(self):
        return subprocess.run(['/usr/local/sbin/opnsense-version', '-v'], capture_output=True, text=True).stdout.strip()

    def suricata_version(self):
        tmp = subprocess.run(['/usr/local/bin/suricata', '-V'], capture_output=True, text=True).stdout.strip()
        if tmp.find(' version '):
            tmp = tmp[tmp.find(' version ')+9:]
        return tmp

    def system_uptime(self):
        tmp = subprocess.run('/usr/bin/uptime', capture_output=True, text=True).stdout.strip()
        tmp = tmp.split(' up ')[-1].split(',')
        if len(tmp) == 6:
            return "%s %s" % (tmp[0].strip(), tmp[1].strip())
        return None

    def suricata_status(self):
        sp = subprocess.run(['/usr/local/etc/rc.d/suricata', 'status'], text=True)
        return 'Running' if sp.returncode == 0 else 'Stopped'

    def system_time(self):
        return int(time.time())

    def ruleset_version(self):
        if os.path.isfile('/usr/local/etc/suricata/rules/telemetry_version.json'):
            with open("/usr/local/etc/suricata/rules/telemetry_version.json") as f_in:
                data = f_in.read()
                if data.startswith('#@opnsense_downlo'):
                    # strip download hash line
                    data = data[data.find('\n')+1:]
                data = ujson.loads(data)
                if 'version' in data:
                    return data['version']
        return None

    def get(self):
        result = dict()
        for item in ['software_version', 'suricata_version', 'suricata_status', 'system_uptime', 'system_time',
                     'ruleset_version']:
            try:
                value = getattr(self, item)()
            except FileNotFoundError:
                value = "NOTFOUND"
            except Exception as e:
                value = "ERROR"
            result[item] = value
        return result
