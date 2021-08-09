"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
    Copyright (C) 2020 Starkstromkonsument
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
import re
import datetime
from . import BaseLogFormat
zabbix_timeformat = r'(\d*):(\d{4}\d{2}\d{2}:\d{2}\d{2}\d{2})\.\d{3}\s(.*)'


class ZabbixLogFormat(BaseLogFormat):
    def __init__(self, filename):
        super(ZabbixLogFormat, self).__init__(filename)
        self._priority = 100

    def match(self, line):
        return self._filename.find('zabbix_agentd') > -1 and re.match(zabbix_timeformat, line) is not  None

    @staticmethod
    def timestamp(line):
        tmp = re.match(zabbix_timeformat, line)
        grp = tmp.group(2)
        return datetime.datetime.strptime(grp, "%Y%m%d:%H%M%S").isoformat()

    @staticmethod
    def process_name(line):
        tmp = re.match(zabbix_timeformat, line)
        return tmp.group(1)

    @staticmethod
    def line(line):
        tmp = re.match(zabbix_timeformat, line)
        return tmp.group(3)
