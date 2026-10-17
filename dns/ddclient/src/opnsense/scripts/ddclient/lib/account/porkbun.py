"""
    Copyright (c) 2026 5t0n3 <x@formulaic.coffee>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
    notice, this list of conditions and the following disclaimer in the
    documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
    "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
    LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
    A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
    HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
    SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
    PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
    OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
    WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
    OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
    ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
"""
import syslog
from typing import Any

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

from . import BaseAccount


_POST_TIMEOUT = 30


class Porkbun(BaseAccount):
    @staticmethod
    def known_services() -> dict[str, str]:
        return {'porkbun': 'Porkbun'}

    @staticmethod
    def match(account: dict[str, Any]) -> bool:
        return account.get('service') == 'porkbun'

    def log(self, level: int, message: Any) -> None:
        syslog.syslog(level, f'Account {self.description} {message}')

    def _get_hostnames(self) -> list[str]:
        hostnames = self.settings.get('hostnames') or ''
        return [h.strip() for h in hostnames.split(',') if h.strip()]

    def _get_zone(self, hostname: str) -> str:
        """Return the DNS zone for a hostname (from config or derived).

        If 'zone' is not explicitly configured, a best-effort fallback is used
        by stripping the leftmost label from hostnames containing more than two
        dot-separated parts.

        Note:
            The fallback is a string-splitting heuristic and can fail in:
            - Multi-level subdomains: 'app.router.example.com' incorrectly
              derives 'router.example.com' instead of 'example.com'.
            - Apex domains on multi-part TLDs: 'example.co.uk' incorrectly
              derives 'co.uk' instead of 'example.co.uk'.

            In these scenarios, the 'zone' field must be explicitly set in the
            account configuration.
        """
        zone = (self.settings.get('zone') or '').strip().rstrip('.')
        if zone:
            return zone
        parts = hostname.split('.')
        if len(parts) > 2:
            return '.'.join(parts[1:])
        return hostname

    @staticmethod
    def _get_label(hostname: str, zone: str) -> str:
        """Return the record label (left of zone) for a hostname."""
        if hostname == zone:
            return ''
        if hostname.endswith('.' + zone):
            return hostname[:-len(zone) - 1]
        return hostname.split('.')[0]

    def _create_record(
        self, s: requests.Session, domain: str, subdomain: str,
        record_type: str, hostname: str
    ) -> bool:
        """Fallback method to create a DNS record if editing fails."""
        create_url = f'https://api.porkbun.com/api/json/v3/dns/create/{domain}'
        create_payload: dict[str, str] = {
            'name': subdomain,
            'type': record_type,
            'content': self.current_address,
            'ttl': '600'
        }

        try:
            create_resp = s.post(create_url, json=create_payload,
                                 timeout=_POST_TIMEOUT)
        except requests.exceptions.RequestException as e:
            self.log(syslog.LOG_ERR,
                     f'network error creating record for {hostname}: {e}')
            return False

        try:
            create_json = create_resp.json()
        except requests.exceptions.JSONDecodeError:
            self.log(
                syslog.LOG_ERR,
                f'error parsing create JSON response '
                f'(host: {hostname}): body {create_resp.text}'
            )
            return False

        if not isinstance(create_json, dict):
            self.log(
                syslog.LOG_ERR,
                f'unexpected non-dict JSON response for create '
                f'(host: {hostname}): type {type(create_json).__name__}'
            )
            return False

        if create_json.get('status') != 'SUCCESS':
            err_msg = create_json.get("message", "unknown create error")
            self.log(
                syslog.LOG_ERR,
                f'failed to create {record_type} for {hostname}: {err_msg}'
            )
            return False

        self.log(
            syslog.LOG_NOTICE,
            f'created new {record_type} record {self.current_address} '
            f'for hostname {hostname}'
        )

        return True

    def execute(self) -> bool:
        if not super().execute():
            return False  # Current address unchanged, or error getting address

        record_type = 'AAAA' if ':' in self.current_address else 'A'

        edit_payload = {
            'content': self.current_address,
            'type': record_type,
        }

        with requests.Session() as s:
            retries = Retry(
                total=4,
                backoff_factor=1,
                status_forcelist=[429],  # Too many requests (rate limit)
                allowed_methods=['POST']
            )
            s.mount('https://', HTTPAdapter(max_retries=retries))

            s.headers['User-Agent'] = 'OPNsense-dyndns'
            s.headers['X-API-Key'] = self.settings.get('username') or ''
            s.headers['X-Secret-API-Key'] = self.settings.get('password') or ''

            for hostname in self._get_hostnames():

                domain: str = self._get_zone(hostname)
                subdomain: str = self._get_label(hostname, domain)

                subdomain_path = f'/{subdomain}' if subdomain else ''
                edit_url = (
                    f'https://api.porkbun.com/api/json/v3/dns/'
                    f'editByNameType/{domain}/{record_type}{subdomain_path}'
                )

                try:
                    edit_resp = s.post(edit_url, json=edit_payload,
                                       timeout=_POST_TIMEOUT)
                except requests.exceptions.RequestException as e:
                    self.log(
                        syslog.LOG_ERR,
                        f'network error editing record for {hostname}: {e}'
                    )
                    return False

                try:
                    edit_json = edit_resp.json()
                except requests.exceptions.JSONDecodeError:
                    self.log(
                        syslog.LOG_ERR,
                        f'error when parsing edit JSON response '
                        f'(host: {hostname}): body {edit_resp.text}'
                    )
                    return False

                if not isinstance(edit_json, dict):
                    self.log(
                        syslog.LOG_ERR,
                        f'unexpected non-dict JSON response for edit '
                        f'(host: {hostname}): type {type(edit_json).__name__}'
                    )
                    return False

                if edit_json.get('status') != 'SUCCESS':
                    err_msg: Any = edit_json.get("message", "unknown error")
                    self.log(
                        syslog.LOG_NOTICE,
                        f'edit failed for {hostname} ({err_msg}), '
                        f'attempting creation...'
                    )
                    if not self._create_record(
                        s, domain, subdomain, record_type, hostname
                    ):
                        return False
                else:
                    self.log(
                        syslog.LOG_NOTICE,
                        f'set new IP {self.current_address} '
                        f'for hostname {hostname}'
                    )

        self.update_state(address=self.current_address)
        return True
