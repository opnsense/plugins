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
import requests
from . import BaseAccount


class Porkbun(BaseAccount):
    @staticmethod
    def known_services():
        return {'porkbun': 'Porkbun'}

    @staticmethod
    def match(account):
        return account.get('service') == 'porkbun'

    def log(self, level, message):
        syslog.syslog(level, f'Account {self.description} {message}')

    def execute(self):
        if super().execute():
            # IPv4/IPv6
            recordType = 'AAAA' if ':' in self.current_address else 'A'

            # use Session object to store constant API request headers
            s = requests.Session()
            s.headers['User-Agent'] = 'OPNsense-dyndns'
            s.headers['X-API-Key'] = self.settings.get('username')
            s.headers['X-Secret-API-Key'] = self.settings.get('password')

            # keep track of which records to update
            updates = []

            # get record IDs for each domain to update
            for hostname in self.settings["hostnames"].split(","):
                # split off domain from subdomain, if present
                split_domain = hostname.rsplit('.', 2)
                if len(split_domain) == 3:
                    subdomain, middle, tld = split_domain
                    domain = f'{middle}.{tld}'
                else:
                    subdomain = ''
                    domain = hostname

                # fetch subdomain A records
                fetch_url = f'https://api.porkbun.com/api/json/v3/dns/retrieveByNameType/{domain}/{recordType}/{subdomain}'
                fetch_resp = s.post(fetch_url)

                try:
                    records_json = fetch_resp.json()
                except requests.exceptions.JSONDecodeError:
                    self.log(syslog.LOG_ERR, f'error when parsing record IDs JSON response (host: {hostname}): body {fetch_resp.text}')
                    return False

                if records_json.get('status') != 'SUCCESS':
                    self.log(
                        syslog.LOG_ERR,
                        f'error fetching {recordType} records for hostname {hostname}: {records_json["message"]}'
                    )
                    return False

                if not records_json['records']:
                    self.log(
                        syslog.LOG_ERR,
                        f'error no {recordType} records found for host {hostname}'
                    )
                    return False

                # arbitrarily choose first record if exists
                record_id = records_json['records'][0]['id']
                updates.append((domain, hostname, record_id))

            # all records have same type (A/AAAA) and new value (IP)
            # NOTE: ttl is explicitly omitted as it caused errors when trying to edit records in testing
            edit_payload = {
                'content': self.current_address,
                'type': recordType,
            }

            # update each record based on ID
            for domain, hostname, record_id in updates:
                edit_url = f'https://api.porkbun.com/api/json/v3/dns/edit/{domain}/{record_id}'
                edit_resp = s.post(edit_url, json=edit_payload)

                try:
                    edit_json = edit_resp.json()
                except requests.exceptions.JSONDecodeError:
                    self.log(
                        syslog.LOG_ERR,
                        f'error when parsing edit JSON response (host: {hostname}): body {edit_resp.text}'
                    )
                    return False

                if edit_json.get('status') != 'SUCCESS':
                    self.log(
                        syslog.LOG_ERR,
                        f'error response when updating {recordType} record for hostname {hostname}: {edit_json["message"]}'
                    )
                    return False
                else:
                    self.log(
                        syslog.LOG_NOTICE,
                        f'set new IP {self.current_address} for hostname {hostname}'
                    )

            self.update_state(address=self.current_address)
            return True

        return False
