"""
    Copyright (c) 2023 Bernhard Frenking <bernhard@frenking.eu>
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
    ----------------------------------------------------------------------------------------------------
    Domeneshop DNS updater
    Token should be set via the username field
    Secret should be set via the password field
"""
import syslog
import requests
from requests.auth import HTTPBasicAuth
from . import BaseAccount


class Domeneshop(BaseAccount):

    _services = {
        'domeneshop': 'api.domeneshop.no'
    }

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  Domeneshop._services.keys()

    @staticmethod
    def match(account):
        return account.get('service') in Domeneshop._services

    def execute(self):
        if super().execute():
            hostnames = self.settings.get('hostnames')

            # DNS update request using the "IP update protocol"
            url = f'https://api.domeneshop.no/v0/dyndns/update?hostname={hostnames}&myip={str(self.current_address)}'
            req_opts = {
                'url': url,
                'auth': HTTPBasicAuth(self.settings.get('username'), self.settings.get('password')),
                'headers': {
                    'User-Agent': 'OPNsense-dyndns'
                }
            }
            response = requests.get(**req_opts)

            # Parse response and update state and log
            if response.status_code is 204:
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s set new ip %s [%s] for %s" % (self.description, self.current_address, response.text.strip(), hostnames)
                    )
                self.update_state(address=self.current_address, status=response.text.split()[0] if response.text else '')
                return True
            elif response.status_code is 404:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to set new ip %s [%d - %s], because %s could not be found" % (
                        self.description, self.current_address, response.status_code, response.text.replace('\n', ''), hostnames
                    )
                )
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to set new ip %s [%d - %s] for %s" % (
                        self.description, self.current_address, response.status_code, response.text.replace('\n', ''), hostnames
                    )
                )

        return False
