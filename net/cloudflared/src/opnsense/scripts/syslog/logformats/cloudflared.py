"""
Copyright (C) 2026 Richard Aspden <rick+github@insanityinside.net>
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
from . import NewBaseLogFormat

# zerolog 3-letter level codes → syslog numeric severity
_SEVERITY = {
    'DBG': 7,
    'INF': 6,
    'WRN': 4,
    'ERR': 3,
    'FTL': 2,
    'PNC': 1,
}

# zerolog: 2026-05-12T11:51:06Z INF message
_RE_ZEROLOG = re.compile(
    r'^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))\s+([A-Z]{3})\s+(.*)'
)

# Go stdlib log (e.g. quic-go): 2026/05/12 13:35:44 message  — no level field
_RE_GOSTDLIB = re.compile(
    r'^(\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2})\s+(.*)'
)


class CloudflaredLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._timestamp = None
        self._severity = None
        self._message = None

    def match(self, line):
        if 'cloudflared' not in self._filename:
            return False
        return bool(_RE_ZEROLOG.match(line) or _RE_GOSTDLIB.match(line))

    def set_line(self, line):
        m = _RE_ZEROLOG.match(line)
        if m:
            ts_raw = m.group(1)
            # Normalise Z suffix so fromisoformat() accepts it (Python < 3.11)
            self._timestamp = ts_raw.replace('Z', '+00:00')
            self._severity = _SEVERITY.get(m.group(2), 6)
            self._message = m.group(3)
            return

        m = _RE_GOSTDLIB.match(line)
        if m:
            # Go stdlib format has no level; default to Informational
            self._timestamp = m.group(1).replace('/', '-', 2).replace(' ', 'T')
            self._severity = 6
            self._message = m.group(2)
            return

        self._timestamp = None
        self._severity = 6
        self._message = line

    @property
    def timestamp(self):
        return self._timestamp

    @property
    def process_name(self):
        # zerolog format has no process field; hardcode so the column isn't blank
        return 'cloudflared'

    @property
    def pid(self):
        return None

    @property
    def severity(self):
        return self._severity

    @property
    def line(self):
        return self._message
