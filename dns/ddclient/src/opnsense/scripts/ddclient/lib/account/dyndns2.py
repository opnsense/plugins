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
from requests.auth import HTTPBasicAuth
from . import BaseAccount


class DynDNS2(BaseAccount):
    _priority = 65535

    _services = {
        'dyndns2': 'members.dyndns.org',
        'desec-v4': 'update.dedyn.io',
        'desec-v6': 'update6.dedyn.io',
        'dns-o-matic': 'updates.dnsomatic.com',
        'dynu': 'api.dynu.com',
        'he-net': 'dyn.dns.he.net',
        'he-net-tunnel': 'ipv4.tunnelbroker.net',
        'inwx': 'dyndns.inwx.com',
        'loopia': 'dyndns.loopia.se',
        'nsupdatev4': 'ipv4.nsupdate.info',
        'nsupdatev6': 'ipv6.nsupdate.info',
        'ovh': 'www.ovh.com',
        'spdyn': 'update.spdyn.de',
        'strato': 'dyndns.strato.com',
        'noip': 'dynupdate.no-ip.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  list(DynDNS2._services.keys()) + ['custom']

    @staticmethod
    def match(account):
        if account.get('service') in DynDNS2.known_services():
            return True
        else:
            return False

    def execute(self):
        if super().execute():
            protocol = self.settings.get('protocol', None)
            if protocol in [ 'get', 'post', 'put' ]:
                url = self.settings.get('server')
                url = url.replace('__MYIP__', self.current_address)
                url = url.replace('__HOSTNAME__', self.settings.get('hostnames'))
                req = requests.request(
                    method=protocol,
                    url=url,
                    headers={'User-Agent': 'OPNsense-dyndns'},
                    auth=HTTPBasicAuth(self.settings.get('username'), self.settings.get('password'))
                )
            else:
                uri_proto = 'https' if self.settings.get('force_ssl', False) else 'http'
                if self.settings.get('service') in self._services:
                    url = "%s://%s/nic/update" % (uri_proto, self._services[self.settings.get('service')])
                else:
                    url = "%s://%s/nic/update" % (uri_proto, self.settings.get('server'))

                req_opts = {
                    'url': url,
                    'params': {
                        'hostname': self.settings.get('hostnames'),
                        'myip': self.current_address,
                        'system': 'dyndns',
                        'wildcard': 'ON' if self.settings.get('wildcard', False) else 'NOCHG'
                    },
                    'auth': HTTPBasicAuth(self.settings.get('username'), self.settings.get('password')),
                    'headers': {
                        'User-Agent': 'OPNsense-dyndns'
                    }
                }
                req = requests.get(**req_opts)

            if 200 <= req.status_code < 300:
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s set new ip %s [%s]" % (self.description, self.current_address, req.text.strip())
                    )

                self.update_state(address=self.current_address, status=req.text.split()[0] if req.text else '')
                return True
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to set new ip %s [%d - %s]" % (
                        self.description, self.current_address, req.status_code, req.text.replace('\n', '')
                    )
                )

        return False
