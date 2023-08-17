"""
    Copyright (c) 2023 Greg Glockner <greg@glockners.net>
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
    ----------------------------------------------------------------------------------------------------
    DuckDNS updater
    Token should be set via the password field
"""
import syslog
import requests
from . import BaseAccount


class duckdns(BaseAccount):
    _services = ['duckdns']

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  duckdns._services

    @staticmethod
    def match(account):
        return account.get('service') in duckdns._services

    def execute(self):
        """ Duck DNS update
        """

        if super().execute():
            data = {
                'domains': self.settings.get('hostnames'),
                'token': self.settings.get('password')
            }

            ip = str(self.current_address)
            if ':' in ip:
                data['ipv6'] = ip
            else:
                data['ip'] = ip

            proto = 'https' if self.settings.get('force_ssl', False) else 'http'

            try:
                response = requests.get(proto+'://www.duckdns.org/update', data)
                if response.text.startswith('KO'):
                    raise RuntimeError(
                        f"DuckDNS update failed for {self.description} with ip {self.current_address} for domains {data['domains']}, response: {response.text}")
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, str(e))
                return False

            syslog.syslog(
                syslog.LOG_NOTICE,
                f"Account {self.description} set new ip {self.current_address} for domains {data['domains']}")

            self.update_state(address=self.current_address)
            return True
