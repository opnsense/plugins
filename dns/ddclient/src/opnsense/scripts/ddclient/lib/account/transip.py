"""
    Copyright (c) 2026 Melvin Groenhoff <melvingroenhoff@gmail.com>
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
from . import BaseAccount
import base64
import cryptography
import cryptography.hazmat.primitives.asymmetric.padding
import cryptography.hazmat.primitives.hashes
import cryptography.hazmat.primitives.serialization
import re
import requests
import secrets
import syslog
import ujson

class TransIP(BaseAccount):
    _priority = 65535

    _services = ['transip']

    _api_base = "https://api.transip.nl/v6"

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return {'transip': 'TransIP'}

    @staticmethod
    def match(account):
        return account.get('service') in TransIP._services

    def execute(self):
        """Update DNS records according to https://api.transip.nl/rest/docs.html#domains-domains"""
        if super().execute():
            domain = self.settings.get('zone')

            access_token = self._get_access_token(self.settings.get('password'))
            if not access_token:
                return False

            dns_entries = self._get_dns_entries(access_token, domain)

            # Determine record type based on the IP format
            record_type = "AAAA" if str(self.current_address).find(':') > -1 else "A"

            # Update DNS records
            record_names = self.settings.get('hostnames', '').split(',')
            records_updated = 0
            for dns_entry in dns_entries:
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Found DNS entry %s (%s)"
                        % (dns_entry["name"], dns_entry["type"])
                    )

                for record_name in record_names:
                    # Only update A/AAAA records
                    if record_name == dns_entry["name"] and record_type == dns_entry["type"]:
                        if dns_entry["content"] != str(self.current_address):
                            content = str(self.current_address)
                            expire = int(self.settings.get('ttl', 300))

                            if self._update_record(access_token, domain, record_name, record_type, content, expire):
                                records_updated += 1

                                syslog.syslog(
                                    syslog.LOG_NOTICE,
                                    "Account %s updated record %s (%s) from %s to %s and ttl from %s to %s" % (
                                        self.description,
                                        record_name,
                                        record_type,
                                        dns_entry["content"],
                                        content,
                                        dns_entry["expire"],
                                        expire
                                    )
                                )

            if records_updated > 0:
                self.update_state(address=self.current_address)
                return True

        return False

    def _normalize_pem(self, data):
        m = re.match(r'(?ims)(-----BEGIN .+-----)(.+)(-----END .+-----)', re.sub(r'[\r\n]', '', data.strip()))
        if not m:
            return None

        groups = list(m.groups())
        groups[1] = groups[1].replace(' ', '')
        return "\n".join(groups)

    def _get_access_token(self, private_key):
        """Retrieve auth token according to https://api.transip.nl/rest/docs.html#header-authentication"""

        private_key = cryptography.hazmat.primitives.serialization.load_pem_private_key(
            self._normalize_pem(private_key).encode(),
            password=None
        )

        data = ujson.dumps({
            "login": self.settings.get('username'),
            "nonce": secrets.token_hex(16),
            "read_only": False,
            "expiration_time": "30 seconds",
            "label": "OPNsense-dyndns",
            "global_key": True # Bypass IP whitelist because that's the whole point of dynamic dns
        })

        signature = private_key.sign(
            data.encode(),
            cryptography.hazmat.primitives.asymmetric.padding.PKCS1v15(),
            cryptography.hazmat.primitives.hashes.SHA512()
        )

        headers = {
            "Content-Type": "application/json",
            "Signature": base64.b64encode(signature)
        }

        response = requests.post(f"{self._api_base}/auth", data=data, headers=headers)

        if 200 < response.status_code >= 300:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error getting auth token: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return None

        try:
            payload = response.json()
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error parsing JSON response: %s" % (self.description, response.text)
            )
            return None

        return payload.get("token")

    def _get_dns_entries(self, access_token, domain):
        url = f"{self._api_base}/domains/{domain}/dns"

        headers = {
            "Authorization": f"Bearer {access_token}"
        }

        response = requests.get(url, headers=headers)

        if 200 < response.status_code >= 300:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error fetching dns entries: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return None

        try:
            payload = response.json()
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error parsing JSON response: %s" % (self.description, response.text)
            )
            return None

        return payload.get("dnsEntries")

    def _update_record(self, access_token, domain, record_name, record_type, content, expire):
        url = f"{self._api_base}/domains/{domain}/dns"

        data = {
            "dnsEntry": {
                "name": record_name,
                "type": record_type,
                "content": content,
                "expire": expire
            }
        }

        headers = {
            "Authorization": f"Bearer {access_token}",
            "Content-Type": "application/json"
        }

        response = requests.patch(url, json=data, headers=headers)

        if 200 < response.status_code >= 300:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error updating dns entry: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return False

        return True
