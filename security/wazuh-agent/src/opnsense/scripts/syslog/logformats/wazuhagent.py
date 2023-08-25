"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
from . import NewBaseLogFormat
ossec_timeformat = r'^(\d{4}/\d{1,2}/\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}).*'

class OssecLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 100

    def match(self, line):
        return self._filename.find('wazuhagent') > -1 and re.match(ossec_timeformat, line) is not  None

    @property
    def timestamp(self):
        tmp = re.match(ossec_timeformat, self._line)
        grp = tmp.group(1)
        return datetime.datetime.strptime(grp, "%Y/%m/%d %H:%M:%S").isoformat()

    @property
    def process_name(self):
        return self._line[19:].strip().split(':', 1)[0]

    @property
    def line(self):
        return self._line[19:].strip().split(':', 1)[-1]
