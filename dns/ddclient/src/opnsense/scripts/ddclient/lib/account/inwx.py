"""
    Copyright (c) 2026 Johannes Nolte <johannes@jonolt.eu>
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
    INWX DNS updater

    Service specific dyndns2-style account. INWX expects an IPv6 address in the
    "myipv6" parameter (setting the AAAA record); the dyndns2 legacy "standard"
    only specifies "myip", so this is kept out of the generic DynDNS2 class.
    For an IPv4 address the request matches the generic DynDNS2 "/nic/update"
    output, aside from the no-op "system" / "wildcard" parameters which INWX
    ignores (its endpoint only reads "hostname", "myip" and "myipv6").

    Record scope is per DynDNS login: a single INWX login bound to both A and
    AAAA drops whichever family is omitted from an update. For dual-stack, use a
    separate INWX login per family (each bound to its own record) — the standard
    OPNsense one-account-per-family model.

    INWX documented update URL:
        https://dyndns.inwx.com/nic/update?myip=<ipaddr>&myipv6=<ip6addr>
"""
import syslog
import requests
from requests.auth import HTTPBasicAuth
from . import BaseAccount


class INWX(BaseAccount):

    _services = {
        'inwx': 'dyndns.inwx.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return INWX._services.keys()

    @staticmethod
    def match(account):
        return account.get('service') in INWX._services

    def execute(self):
        if super().execute():
            uri_proto = 'https' if self.settings.get('force_ssl', False) else 'http'
            url = "%s://%s/nic/update" % (uri_proto, self._services['inwx'])

            # INWX takes an IPv6 address (contains ':') as "myipv6", which sets the
            # AAAA record; IPv4 goes to "myip" exactly like the generic DynDNS2 class.
            ip = str(self.current_address)
            ip_param = 'myipv6' if ':' in ip else 'myip'

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s sending INWX update using parameter %s" % (self.description, ip_param)
                )

            req_opts = {
                'url': url,
                'params': {
                    'hostname': self.settings.get('hostnames'),
                    ip_param: self.current_address
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

                # Per-login record scope — see module docstring; warn the operator below.
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s set %s (%s). For INWX dual-stack use a separate INWX DynDNS "
                    "login per family (each bound to its own A/AAAA record); a single INWX "
                    "login covering both records drops the family not sent in a given update" % (
                        self.description, ip_param, 'AAAA' if ip_param == 'myipv6' else 'A'
                    )
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
