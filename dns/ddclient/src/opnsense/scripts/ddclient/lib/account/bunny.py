"""
    Copyright (c) 2026 Theodoros Orfanidis <teoulas@gmail.com>
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

    bunny.net DNS provider for the OPNsense native Dynamic DNS backend.

    API specification: https://core-api-public-docs.b-cdn.net/docs/v3/public.json

    UI fields:
      zone      - DNS zone domain
      password  - API access key
      hostnames - FQDN(s) to update, comma-separated
"""
import syslog

import requests

from . import BaseAccount


class Bunny(BaseAccount):
    """Update existing bunny.net A and AAAA records."""
    _priority = 65535
    _services = {'bunny': 'api.bunny.net'}

    @staticmethod
    def known_services():
        return {'bunny': 'bunny.net'}

    @staticmethod
    def match(account):
        return account.get('service') in Bunny._services

    def _get_items(self, url, headers, params, resource):
        items = []
        page = 1

        while True:
            params['page'] = page
            response = requests.get(url, headers=headers, params=params)
            if response.status_code != 200:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to fetch %s: HTTP %d - %s" % (
                        self.description, resource, response.status_code, response.text.replace('\n', '')
                    )
                )
                return None

            try:
                payload = response.json()
            except ValueError:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to parse %s response: %s" % (
                        self.description, resource, response.text.replace('\n', '')
                    )
                )
                return None

            page_items = payload.get('Items')
            if not isinstance(page_items, list):
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s %s response has no item list" % (self.description, resource)
                )
                return None

            items.extend(page_items)
            if not payload.get('HasMoreItems', False):
                return items
            if not page_items:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s %s pagination did not advance" % (self.description, resource)
                )
                return None
            page += 1

    def _get_zone(self, zone_domain, headers):
        url = 'https://%s/dnszone' % self._services[self.settings.get('service')]
        items = self._get_items(
            url,
            headers,
            {'perPage': 1000, 'search': zone_domain},
            'DNS zone'
        )
        if items is None:
            return None

        matches = [
            item for item in items
            if str(item.get('Domain', '')).strip().rstrip('.').lower() == zone_domain.lower()
        ]
        if not matches:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s could not find DNS zone %s" % (self.description, zone_domain)
            )
            return None
        if len(matches) > 1:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s found multiple exact matches for DNS zone %s" % (self.description, zone_domain)
            )
            return None

        zone_id = matches[0].get('Id')
        domain = str(matches[0].get('Domain', '')).strip().rstrip('.')
        if zone_id is None or not domain:
            syslog.syslog(syslog.LOG_ERR, "Account %s DNS zone response is incomplete" % self.description)
            return None
        return str(zone_id), domain

    def _list_records(self, zone_id, record_type, headers):
        url = 'https://%s/dnszone/%s/records' % (self._services[self.settings.get('service')], zone_id)
        return self._get_items(
            url,
            headers,
            {'perPage': 1000, 'type': record_type},
            'DNS record'
        )

    @staticmethod
    def _record_fqdn(record_name, zone_domain):
        name = str(record_name or '').strip().rstrip('.').lower()
        domain = zone_domain.strip().rstrip('.').lower()
        if name in ['', '@']:
            return domain
        if name == domain or name.endswith('.' + domain):
            return name
        return '%s.%s' % (name, domain)

    def _update_record(self, zone_id, record_id, headers):
        response = requests.post(
            'https://%s/dnszone/%s/records/%s' % (
                self._services[self.settings.get('service')], zone_id, record_id
            ),
            headers=headers,
            json={'Value': str(self.current_address)}
        )
        if response.status_code != 204:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s failed to update DNS record %s: HTTP %d - %s" % (
                    self.description, record_id, response.status_code, response.text.replace('\n', '')
                )
            )
            return False
        return True

    def execute(self):
        if not super().execute():
            return False

        configured_zone = str(self.settings.get('zone', '')).strip().rstrip('.')
        if not configured_zone:
            syslog.syslog(syslog.LOG_ERR, "Account %s has no DNS zone configured" % self.description)
            return False
        if not str(self.settings.get('password', '')).strip():
            syslog.syslog(syslog.LOG_ERR, "Account %s has no API key" % self.description)
            return False

        hostnames = [
            hostname.strip().rstrip('.').lower()
            for hostname in self.settings.get('hostnames', '').split(',')
            if hostname.strip()
        ]
        if not hostnames:
            syslog.syslog(syslog.LOG_ERR, "Account %s has no hostnames configured" % self.description)
            return False

        headers = {
            'User-Agent': 'OPNsense-dyndns',
            'AccessKey': self.settings.get('password', '')
        }
        zone = self._get_zone(configured_zone, headers)
        if zone is None:
            return False
        zone_id, zone_domain = zone

        record_type, record_type_name = (1, 'AAAA') if ':' in str(self.current_address) else (0, 'A')
        records = self._list_records(zone_id, record_type, headers)
        if records is None:
            return False

        all_success = True
        for hostname in hostnames:
            matches = [
                record for record in records
                if record.get('Type') == record_type and
                self._record_fqdn(record.get('Name'), zone_domain) == hostname
            ]
            if not matches:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s could not find hostname %s with record type %s" % (
                        self.description, hostname, record_type_name
                    )
                )
                all_success = False
                continue
            if len(matches) > 1:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s found multiple records for hostname %s with record type %s" % (
                        self.description, hostname, record_type_name
                    )
                )
                all_success = False
                continue

            record_id = matches[0].get('Id')
            if record_id is None or not self._update_record(zone_id, record_id, headers):
                all_success = False
                continue

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s set new IP %s for %s" % (self.description, self.current_address, hostname)
                )

        if all_success:
            self.update_state(address=self.current_address)
            return True
        return False
