#!/usr/local/bin/python3

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

import os
from . import BaseBlocklistHandler

class DefaultBlocklistHandler(BaseBlocklistHandler):
    def __init__(self):
        super().__init__('/usr/local/etc/unbound/qfeeds-blocklists.conf')
        self.priority = 50

    def get_config(self):
        cfg = {'qfeeds_filenames': []}
        if self.cnf and self.cnf.has_section('settings'):
            if self.cnf.has_option('settings', 'filenames'):
                cfg['qfeeds_filenames'] = self.cnf.get('settings', 'filenames').split(',')
        return cfg

    def get_blocklist(self):
        # Only return domains if integration is enabled
        if not self._is_enabled():
            return {}  # Return empty blocklist when disabled
        
        result = {}
        for filename in self.get_config()['qfeeds_filenames']:
            bl_shortcode = "qf_%s" % os.path.splitext(os.path.basename(filename).strip())[0]
            if os.path.exists(filename):
                with open(filename, 'r') as f_in:
                    for line in f_in:
                        result[line.strip()] = {'bl': bl_shortcode, 'wildcard': False}
        return result

    def get_passlist_patterns(self):
        return []
    
    def _is_enabled(self):
        """Check if unbound blocklist integration is enabled"""
        try:
            import subprocess
            result = subprocess.run(['/usr/local/sbin/configctl', 'config', 'get', 
                                   'OPNsense.QFeedsConnector.general.enable_unbound_bl'], 
                                  capture_output=True, text=True)
            return result.stdout.strip() == '1'
        except:
            return False
