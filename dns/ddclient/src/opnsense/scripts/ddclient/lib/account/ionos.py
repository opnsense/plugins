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

import syslog
import requests
from . import BaseAccount


class Ionos(BaseAccount):
    _priority = 100

    _services = {
        'ionos-v4': 'https://ipv4.api.hosting.ionos.com/dns/v1/dyndns',
        'ionos-v6': 'https://ipv4.api.hosting.ionos.com/dns/v1/dyndns'
    }

    @staticmethod
    def known_services():
        return list(Ionos._services.keys())

    @staticmethod
    def match(account):
        return account.get('service') in Ionos.known_services()

    def execute(self):
        if super().execute():
            service = self.settings.get('service')
            params = {
                'q': self.settings.get('password')
            }
            if service == 'ionos-v6':
                params['ipv6'] = self.current_address

            req = requests.get(
                url=self._services[service],
                params=params,
                headers={'User-Agent': 'OPNsense-dyndns'}
            )

            if 200 <= req.status_code < 300:
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s set new ip %s [%s]" % (self.description, self.current_address, req.text.strip())
                    )
                self.update_state(address=self.current_address, status=req.text.split()[0] if req.text else 'OK')
                return True
            else:
                syslog.syslog(
                    syslog.LOG_ERR,"Account %s failed to set new ip %s [%d - %s]" % (
                    self.description, self.current_address, req.status_code, req.text.replace('\n', '')
                    )
                )
        return False
