"""
    Copyright (c) 2025 Deciso B.V.
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

import glob
import time
import subprocess
import ipaddress

def is_ip_address(value):
    try:
        ipaddress.ip_address(value)
        return True
    except ValueError:
        return False


class PFLogCrawler:
    def __init__(self, table_names:list=[]):
        self._table_names = table_names
        self._rule_ids = set()
        self._collect_rule_ids()

    def _collect_rule_ids(self):
        self._rule_ids = set()
        sp = subprocess.run(['/sbin/pfctl', '-sr'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            for table in self._table_names:
                if line.find("<%s>" % table) > 0:
                    self._rule_ids.add(line.split()[-1].strip('"'))

    @staticmethod
    def _parse_log_line(line):
        # quick scan for datetime, interface, direction, source, dest
        parts = line.split()
        fw_line = parts[-1].split(',') # strip syslog
        return [parts[1], fw_line[4], fw_line[7]] + [x for x in fw_line if is_ip_address(x)]

    def find(self, max_time=60, max_results=50000):
        result = []
        start_time = time.time()
        rows_processed = 0
        for filename in sorted(glob.glob("/var/log/filter/filter_*.log"), reverse=True):
            with open(filename) as f_in:
                for idx, line in enumerate(f_in):
                    for rule_id in self._rule_ids:
                        if rule_id in line:
                            result.append(self._parse_log_line(line))
                            rows_processed +=1
                            break # inner loop
                    if (idx % 100000 == 0 and time.time() - start_time > max_time) or rows_processed >= max_results:
                        return result

        return result
