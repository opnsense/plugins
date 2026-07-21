#!/usr/local/bin/python3

"""
    Copyright (c) 2025-2026 Deciso B.V.
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
import re
import syslog
import uuid
from . import BaseBlocklistHandler

class QFeedsBlocklistHandler(BaseBlocklistHandler):
    def __init__(self):
        super().__init__('/usr/local/etc/unbound/qfeeds-blocklists.conf')
        self.priority = 50
        self._compat_id = str(uuid.uuid4())

    def _is_enabled(self):
        return self.cnf and self.cnf.has_section('settings') and self.cnf.has_option('settings', 'filenames')

    def get_config(self):
        # do not use, unbound worker settings
        return {}

    def get_blocklist(self):
        # Only return domains if integration is enabled (filenames are offered)
        qfeeds_filenames = []
        if self._is_enabled():
            qfeeds_filenames = self.cnf.get('settings', 'filenames').split(',')
            # touch a file to help qfeedsctl detect the current instance uses its list
            open('/tmp/qfeeds-unbound-bl.stat', 'w').write('')

        result = {}
        for filename in qfeeds_filenames:
            bl_shortcode = "qf_%s" % os.path.splitext(os.path.basename(filename).strip())[0]
            if os.path.exists(filename):
                with open(filename, 'r') as f_in:
                    for line in f_in:
                        result[line.strip()] = {'bl': bl_shortcode, 'wildcard': False}
        return result

    def get_passlist_patterns(self):
        return []

    def get_policies(self):
        if not self._is_enabled():
            return []

        cfg = {
            'source_nets': [],
            'address': '',
            'rcode': '',
            'id': self._compat_id,
            'allowlists': []
        }
        for k,v in self.cnf['settings'].items():
            if k in cfg and v.strip() != '':
                if type(cfg[k]) is list:
                    cfg[k] = v.split(',')
                else:
                    cfg[k] = v.strip()

        if cfg['allowlists']:
            compiled_passlist = set()
            for pattern in cfg['allowlists']:
                try:
                    re.compile(pattern, re.IGNORECASE)
                    compiled_passlist.add(pattern)
                except re.error:
                    syslog.syslog(syslog.LOG_ERR,'Q-Feeds : skip invalid whitelist exclude pattern "%s"' % pattern)

            cfg['passlist'] = '|'.join(compiled_passlist)
            del cfg['allowlists']

        return [cfg]
