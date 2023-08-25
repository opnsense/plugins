"""
    Copyright (c) 2023 Thomas Cekal <thomas@cekal.org>
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
import json
import syslog
import requests
from . import BaseAccount


class Cloudflare(BaseAccount):
    _priority = 65535

    _services = {
        'cloudflare': 'api.cloudflare.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  Cloudflare._services.keys()

    @staticmethod
    def match(account):
        return account.get('service') in Cloudflare._services

    def execute(self):
        if super().execute():
            # IPv4/IPv6
            recordType = None
            if str(self.current_address).find(':') > 1:
                #IPv6
                recordType = "AAAA"
            else:
                #IPv4
                recordType = "A"

            # get ZoneID
            url = "https://%s/client/v4/zones" % self._services[self.settings.get('service')]

            headers = {
                'User-Agent': 'OPNsense-dyndns'
            }
            # switch between bearer and email/key authentication
            if self.settings.get('username', '').find('@') == -1:
                headers["Authorization"] = "Bearer " + self.settings.get('password')
            else:
                headers["X-Auth-Email"] = self.settings.get('username')
                headers["X-Auth-Key"] = self.settings.get('password')

            req_opts = {
                'url': url,
                'params': {
                    'name': self.settings.get('zone')
                },
                'headers': headers
            }
            response = requests.get(**req_opts)
            try:
                payload = response.json()
            except requests.exceptions.JSONDecodeError:
                payload = {}
            if 'success' not in payload:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error parsing JSON response [ZoneID] %s" % (self.description, response.text)
                    )
                return False
            if not payload.get('success', False):
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s error receiving ZoneID [%s]" % (self.description, json.dumps(payload.get('errors', {})))
                )
                return False

            zone_id = payload['result'][0]['id']
            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s ZoneID for %s %s" % (self.description, self.settings.get('zone'), zone_id)
                )

            # Get record ID
            req_opts = {
                'url': f"{req_opts['url']}/{zone_id}/dns_records",
                'params': {
                    'name': self.settings.get('hostnames'),
                    'type': recordType
                },
                'headers': req_opts['headers']
            }
            response = requests.get(**req_opts)
            try:
                payload = response.json()
            except requests.exceptions.JSONDecodeError:
                payload = {}
            if 'success' not in payload:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error parsing JSON response [RecordID] %s" % (self.description, response.text)
                    )
                return
            if not payload.get('success', False):
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s error receiving RecordID [%s]" % (
                        self.description, json.dumps(payload.get('errors', {}))
                    )
                )
                return False

            if len(payload['result']) == 0:
                syslog.syslog(
                    syslog.LOG_ERR, "Account %s error locating hostname %s [%s]" % (
                        self.description, self.settings.get('hostnames'), recordType
                    )
                )
                return False

            record_id = payload['result'][0]['id']
            proxied = payload['result'][0]['proxied']
            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s RecordID for %s %s" % (self.description, self.settings.get('hostnames'), record_id)
                )

            # Send IP address update
            req_opts = {
                'url': f"{req_opts['url']}/{record_id}",
                'json': {
                    'type': recordType,
                    'name': self.settings.get('hostnames'),
                    'content': str(self.current_address),
                    'proxied': proxied
                },
                'headers': req_opts['headers']
            }
            response = requests.put(**req_opts)
            try:
                payload = response.json()
            except requests.exceptions.JSONDecodeError:
                payload = {}
            if 'success' not in payload:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error parsing JSON response [UpdateIP] %s" % (self.description, response.text)
                    )
                return False
            if payload.get('success', False):
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s set new ip %s [%s]" % (
                        self.description,
                        self.current_address,
                        payload.get('result', {}).get('content', '')
                    )
                )

                self.update_state(address=self.current_address)
                return True
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to set new ip %s [%s]" % (self.description, self.current_address, response.text)
                )


        return False
