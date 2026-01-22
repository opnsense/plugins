"""
    Copyright (c) 2026 Leandro Scardua
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

# Hostinger DNS API documentation:
# https://developers.hostinger.com/#tag/dns-zone/PUT/api/dns/v1/zones/{domain}

class Hostinger(BaseAccount):
    _services = {
        'hostinger': 'developers.hostinger.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return Hostinger._services.keys()

    @staticmethod
    def match(account):
        return account.get('service') in Hostinger._services

    def execute(self):
        if super().execute():
            # IPv4/IPv6
            recordType = "AAAA" if str(self.current_address).find(':') > 1 else "A"
            
            # Validate TTL
            ttl = int(self.settings.get('ttl', 300)) if (60 <= int(self.settings.get('ttl', 300)) <= 86400) else 300

            # Use bearer authentication
            url = "https://developers.hostinger.com/api/dns/v1/zones/" + self.settings.get('zone')
            
            # Build the zone update payload
            payload = {
                "overwrite": True,
                "zone": [
                    {
                        "name": self.settings.get('hostnames'),
                        "type": recordType,
                        "ttl": ttl,
                        "records": [
                            {
                                "content": self.current_address
                            }
                        ]
                    }
                ]
            }
            
            headers = {
                'authorization': "Bearer " + self.settings.get('password'),
                'content-type': "application/json",
                'User-Agent': 'OPNsense-dyndns'
            }
            
            # Send IP address update
            req = requests.request("PUT", url, data=json.dumps(payload), headers=headers)
            if 200 <= req.status_code < 300:
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s set new ip %s [%s]" % (self.description, self.current_address, req.text.strip())
                    )

                self.update_state(address=self.current_address, status='success')
                return True
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to set new ip %s [%d - %s]" % (
                        self.description, self.current_address, req.status_code, req.text.replace('\n', '')
                    )
                )

        return False