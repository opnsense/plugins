"""
    Copyright (c) 2022 Robbert Rijkse
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
import datetime
import re
from . import NewBaseLogFormat

class BindGeneralLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 1
        self._parts = list()

    def match(self, line):
        return self._filename.find('named/named.log') > -1

    def set_line(self, line):
        super().set_line(line)
        self._parts = self._line.split(maxsplit=4)

    @property
    def timestamp(self):
        # bind format return actual log data
        ts = datetime.datetime.strptime(f"{self._parts[0]} {self._parts[1]}", "%d-%b-%Y %H:%M:%S.%f")
        return ts.isoformat()

    @property
    def severity(self):
        # Grab the log level
        severity = self._parts[3].strip(":")
        options = {
            "critical": 2,
            "error": 3,
            "warning": 4,
            "notice": 5,
            "info": 6,
            "debug": 7,
            "dynamic": 7
        }
        if severity in options:
            return options[severity]
        return None

    @property
    def process_name(self):
        # Grab the type of log message
        return self._parts[2].strip(":")

    @property
    def line(self):
        # Only grab the left over message
        return self._parts[4].strip()

class BindQueryLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 1
        self._parts = list()

    def match(self, line):
        return self._filename.find('named/query.log') > -1 or self._filename.find('named/rpz.log') > -1

    def set_line(self, line):
        super().set_line(line)
        self._parts = self._line.split(maxsplit=7)

    @property
    def timestamp(self):
        # bind format return actual log data
        ts = datetime.datetime.strptime(f"{self._parts[0]} {self._parts[1]}", "%d-%b-%Y %H:%M:%S.%f")
        return ts.isoformat()

    @property
    def pid(self):
        # Grab the IP and Port number of the client
        # pid is used for this because you can't define custom names
        return self._parts[4].strip()

    @property
    def facility(self):
        # Grab the record the query was for
        # facility is used for this because you can't define custom names
        return self._parts[5].strip()[1:-2]

    @property
    def process_name(self):
        # Grab the client memory ID
        # process_name is used for this because you can't define custom names
        return self._parts[3].strip()

    @property
    def line(self):
        # Only grab the left over message
        return self._parts[7].strip()
