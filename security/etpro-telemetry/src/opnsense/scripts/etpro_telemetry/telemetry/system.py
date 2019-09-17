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
from .log import reverse_log_reader

class Stats:
    def __init__(self):
        self._suricata_default_rule_path = '/usr/local/etc/suricata/opnsense.rules'
        self._suricata_installed_rules = '/usr/local/etc/suricata/installed_rules.yaml'
        self._our_sids = telemetry_sids()
        self._installed_sids = self._fetch_installed_sids()

    def _fetch_installed_sids(self):
        installed_sids = set()
        if os.path.isfile(self._suricata_installed_rules):
            with open(self._suricata_installed_rules) as fin:
                for line in fin:
                    line = line.strip()
                    if line.endswith('.rules') and line.startswith('- '):
                        rule_path = '%s/%s' % (self._suricata_default_rule_path, line[2:].strip())
                        if os.path.isfile(rule_path):
                            with open(rule_path) as rf:
                                for rline in rf:
                                    rline = rline.strip()
                                    if not rline.startswith('#'):
                                        sid_ref = rline.rfind('sid:')
                                        if sid_ref > 0:
                                            sid = rline[sid_ref+4:].split(';')[0]
                                            if sid.isdigit():
                                                installed_sids.add(int(sid))
        return installed_sids

    @staticmethod
    def software_version():
        return subprocess.run(['/usr/local/sbin/opnsense-version', '-v'], capture_output=True, text=True).stdout.strip()

    @staticmethod
    def suricata_version():
        tmp = subprocess.run(['/usr/local/bin/suricata', '-V'], capture_output=True, text=True).stdout.strip()
        if tmp.find(' version '):
            tmp = tmp[tmp.find(' version ')+9:]
        return tmp

    @staticmethod
    def suricata_status():
        sp = subprocess.run(['/usr/local/etc/rc.d/suricata', 'status'], capture_output=True, text=True)
        return 'Running' if sp.returncode == 0 else 'Stopped'

    @staticmethod
    def system_time():
        return int(time.time())

    @staticmethod
    def ruleset_version():
        if os.path.isfile('/usr/local/etc/suricata/rules/telemetry_version.json'):
            with open('/usr/local/etc/suricata/rules/telemetry_version.json') as f_in:
                data = f_in.read()
                if data.startswith('#@opnsense_downlo'):
                    # strip download hash line
                    data = data[data.find('\n')+1:]
                data = ujson.loads(data)
                if 'version' in data:
                    return data['version']
        return None

    def total_enabled_rules(self):
        return len(self._installed_sids)

    def total_enabled_telemetry_rules(self):
        return len(self._installed_sids & self._our_sids)

    @staticmethod
    def mode():
        # quick scan config for inline (ips) mode
        conf = '/usr/local/etc/suricata/suricata.yaml'
        if os.path.isfile(conf):
            with open(conf) as fin:
                for line in fin:
                    if line.startswith('  inline: true'):
                        return "IPS"
        return "IDS"

    @staticmethod
    def log_stats():
        # tail stats.log, return statistcs of interest
        result = dict()
        stats_of_interest = ['capture.kernel_packets', 'decoder.pkts', 'decoder.bytes', 'decoder.ipv4', 'decoder.ipv6',
                             'flow.tcp', 'flow.udp', 'detect.alert']
        if os.path.isfile('/var/log/suricata/stats.log'):
            for line in reverse_log_reader('/var/log/suricata/stats.log'):
                if line.strip().startswith('------'):
                    break
                elif line.count('|') == 2:
                    parts = [x.strip() for x in line.split('|')]
                    if parts[0] in stats_of_interest:
                        result[parts[0]] = int(parts[2]) if parts[2].isdigit() else parts[2]
            # add empty values for stats_of_interest not found
            for item in stats_of_interest:
                if item not in result:
                    result[item] = None

        return result

    def get(self):
        result = dict()
        for item in ['software_version', 'suricata_version', 'suricata_status', 'system_time',
                     'ruleset_version', 'total_enabled_rules', 'total_enabled_telemetry_rules' ,'mode', 'log_stats']:
            try:
                value = getattr(self, item)()
            except FileNotFoundError:
                value = 'NOTFOUND'
            except Exception as e:
                value = 'ERROR'
            result[item] = value
        return result
