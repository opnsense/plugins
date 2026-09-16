"""
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
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

    Hetzner DNS Console (Legacy) API provider for OPNsense DynDNS
    Uses the old API at dns.hetzner.com - will be shut down May 2026
    For zones not yet migrated to Hetzner Cloud Console
"""
import syslog
import requests
from . import BaseAccount


class HetznerLegacy(BaseAccount):
    _priority = 65535

    _services = {
        'hetzner': 'dns.hetzner.com'
    }

    _api_base = "https://dns.hetzner.com/api/v1"

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        # Match the existing 'hetzner' service key from DynDNS.xml
        return {'hetzner': 'Hetzner DNS Console'}

    @staticmethod
    def match(account):
        return account.get('service') in HetznerLegacy._services

    def _get_headers(self):
        return {
            'User-Agent': 'OPNsense-dyndns',
            'Auth-API-Token': self.settings.get('password', ''),
            'Content-Type': 'application/json'
        }

    def _get_zone_name(self):
        """Get zone name from settings - try 'zone' field first, then 'username' as fallback"""
        zone_name = self.settings.get('zone', '').strip()
        if not zone_name:
            # Fallback to username for backwards compatibility
            zone_name = self.settings.get('username', '').strip()
        return zone_name

    def _get_zone_id(self, headers):
        """Get zone ID by zone name"""
        zone_name = self._get_zone_name()

        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s looking for zone '%s' (zone field: '%s', username field: '%s')" % (
                    self.description,
                    zone_name,
                    self.settings.get('zone', ''),
                    self.settings.get('username', '')
                )
            )

        url = f"{self._api_base}/zones"
        response = requests.get(url, headers=headers)

        if response.status_code != 200:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error fetching zones: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return None

        try:
            payload = response.json()
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error parsing JSON response [zones]: %s" % (self.description, response.text)
            )
            return None

        zones = payload.get('zones', [])
        for zone in zones:
            if zone.get('name') == zone_name:
                zone_id = zone.get('id')
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s found zone ID %s for %s" % (self.description, zone_id, zone_name)
                    )
                return zone_id

        syslog.syslog(
            syslog.LOG_ERR,
            "Account %s zone '%s' not found" % (self.description, zone_name)
        )
        return None

    def _get_record_id(self, headers, zone_id, record_name, record_type):
        """Get record ID by name and type"""
        url = f"{self._api_base}/records"
        params = {'zone_id': zone_id}

        response = requests.get(url, headers=headers, params=params)

        if response.status_code != 200:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error fetching records: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return None

        try:
            payload = response.json()
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error parsing JSON response [records]: %s" % (self.description, response.text)
            )
            return None

        records = payload.get('records', [])
        for record in records:
            if record.get('name') == record_name and record.get('type') == record_type:
                record_id = record.get('id')
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s found record ID %s for %s %s" % (
                            self.description, record_id, record_name, record_type
                        )
                    )
                return record_id

        return None

    def _update_record(self, headers, zone_id, record_id, record_name, record_type, address):
        """Update existing record with new address"""
        url = f"{self._api_base}/records/{record_id}"

        data = {
            'zone_id': zone_id,
            'type': record_type,
            'name': record_name,
            'value': str(address),
            'ttl': int(self.settings.get('ttl', 300))
        }

        response = requests.put(url, headers=headers, json=data)

        if response.status_code != 200:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error updating record: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return False

        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s updated %s %s to %s" % (
                    self.description, record_name, record_type, address
                )
            )

        return True

    def _create_record(self, headers, zone_id, record_name, record_type, address):
        """Create new record"""
        url = f"{self._api_base}/records"

        data = {
            'zone_id': zone_id,
            'type': record_type,
            'name': record_name,
            'value': str(address),
            'ttl': int(self.settings.get('ttl', 300))
        }

        response = requests.post(url, headers=headers, json=data)

        if response.status_code not in [200, 201]:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error creating record: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return False

        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s created %s %s with %s" % (
                    self.description, record_name, record_type, address
                )
            )

        return True

    def _extract_record_name(self, hostname, zone_name):
        """Extract record name from hostname, handling FQDN format"""
        # Remove trailing dot if present
        hostname = hostname.rstrip('.')

        # Extract record name from FQDN if needed
        if hostname.endswith('.' + zone_name):
            record_name = hostname[:-len(zone_name) - 1]
        elif hostname == zone_name:
            record_name = '@'
        else:
            record_name = hostname

        # Handle root domain
        if not record_name or record_name == '@':
            record_name = '@'

        return record_name

    def execute(self):
        if super().execute():
            record_type = "AAAA" if ':' in str(self.current_address) else "A"
            headers = self._get_headers()

            # Get zone ID
            zone_id = self._get_zone_id(headers)
            if not zone_id:
                return False

            zone_name = self._get_zone_name()

            # Get hostnames - can be comma-separated list
            hostnames_raw = self.settings.get('hostnames', '')
            hostnames = [h.strip() for h in hostnames_raw.split(',') if h.strip()]

            if not hostnames:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s no hostnames configured" % self.description
                )
                return False

            all_success = True
            for hostname in hostnames:
                record_name = self._extract_record_name(hostname, zone_name)

                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s updating %s (record: %s, type: %s) to %s" % (
                            self.description, hostname, record_name, record_type, self.current_address
                        )
                    )

                # Check if record exists
                record_id = self._get_record_id(headers, zone_id, record_name, record_type)

                if record_id:
                    success = self._update_record(
                        headers, zone_id, record_id, record_name, record_type, self.current_address
                    )
                else:
                    success = self._create_record(
                        headers, zone_id, record_name, record_type, self.current_address
                    )

                if success:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s set new IP %s for %s" % (
                            self.description, self.current_address, hostname
                        )
                    )
                else:
                    all_success = False

            if all_success:
                self.update_state(address=self.current_address)
                return True

        return False
