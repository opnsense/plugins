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
import requests
from configparser import ConfigParser


class QFeedsConfig:
    api_key = None

    def __init__(self):
        config_filename = '/usr/local/etc/qfeeds.conf'
        if os.path.isfile(config_filename):
            cnf = ConfigParser()
            cnf.read(config_filename)
            if cnf.has_section('api') and cnf.has_option('api', 'key'):
                self.api_key = cnf.get('api', 'key')


class Api:
    def __init__(self):
        self.api_key = QFeedsConfig().api_key

    def licenses(self):
        r = requests.get(
            url='https://api.qfeeds.com/licenses.php',
            auth=('api_token', self.api_key),
            timeout=60,
            headers={'User-Agent': 'Q-Feeds_OPNsense'}
        )
        r.raise_for_status()
        return r.json()

    def fetch(self, feed):
        r = requests.get(
            url='https://api.qfeeds.com/api.php',
            params={'feed_type': feed},
            auth=('api_token', self.api_key),
            headers={'User-Agent': 'Q-Feeds_OPNsense'},
            stream=True,
            timeout=60
        )
        r.raise_for_status()
        for line in r.raw:
            entry = line.decode().strip()
            if entry:
                yield entry
