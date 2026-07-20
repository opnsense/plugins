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

    Hetzner Cloud DNS API provider for OPNsense DynDNS
    Uses the new Cloud API (api.hetzner.cloud) with proper rrset-actions endpoints
"""
import syslog
import time
import requests
from . import BaseAccount

ACTION_POLL_INTERVAL = 0.5  # seconds between action status polls
ACTION_MAX_WAIT = 30  # maximum seconds to wait for action


class HetznerCloud(BaseAccount):
    _priority = 65535

    _services = {
        'hetznercloud': 'api.hetzner.cloud'
    }

    _api_base = "https://api.hetzner.cloud/v1"

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        # This is dynamically loaded by AccountFactory and added to the service dropdown
        return {'hetznercloud': 'Hetzner Cloud DNS'}

    @staticmethod
    def match(account):
        return account.get('service') in HetznerCloud._services

    def _get_headers(self):
        return {
            'User-Agent': 'OPNsense-dyndns',
            'Authorization': 'Bearer ' + self.settings.get('password', ''),
            'Content-Type': 'application/json'
        }

    def _wait_for_action(self, headers, action_id):
        """Wait for an async action to complete."""
        start_time = time.time()

        while time.time() - start_time < ACTION_MAX_WAIT:
            url = f"{self._api_base}/actions/{action_id}"
            response = requests.get(url, headers=headers)

            if response.status_code != 200:
                return False

            try:
                data = response.json()
                action = data.get('action', {})
                status = action.get('status', '')

                if status == 'success':
                    return True
                elif status == 'error':
                    return False
                elif status in ['running', 'pending']:
                    time.sleep(ACTION_POLL_INTERVAL)
                    continue
                else:
                    return True  # Unknown status, assume success
            except Exception:
                return False

        return False  # Timeout

    def _get_zone_name(self):
        """Get zone name from settings - try 'zone' field first, then 'username' as fallback"""
        zone_name = self.settings.get('zone', '').strip()
        if not zone_name:
            zone_name = self.settings.get('username', '').strip()
        return zone_name

    def _get_zone_id(self, headers):
        """Get zone ID by zone name"""
        zone_name = self._get_zone_name()

        url = f"{self._api_base}/zones"
        params = {'name': zone_name}

        response = requests.get(url, headers=headers, params=params)

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
        if not zones:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s zone '%s' not found" % (self.description, zone_name)
            )
            return None

        zone_id = zones[0].get('id')
        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s found zone ID %s for %s" % (self.description, zone_id, zone_name)
            )

        return zone_id

    def _get_record(self, headers, zone_id, record_name, record_type):
        """Get existing record by name and type"""
        url = f"{self._api_base}/zones/{zone_id}/rrsets/{record_name}/{record_type}"

        response = requests.get(url, headers=headers)

        if response.status_code == 404:
            return None

        if response.status_code != 200:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error fetching record: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return None

        try:
            payload = response.json()
            return payload.get('rrset')
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error parsing JSON response [record]: %s" % (self.description, response.text)
            )
            return None

    def _update_record(self, headers, zone_id, record_name, record_type, address):
        """Update existing record with new address using set_records action.

        Uses the proper rrset-actions endpoint which correctly updates RRsets.
        Actions are async and will be waited upon for completion.
        """
        url = f"{self._api_base}/zones/{zone_id}/rrsets/{record_name}/{record_type}/actions/set_records"

        data = {
            'records': [{'value': str(address)}],
            'ttl': int(self.settings.get('ttl', 300))
        }

        response = requests.post(url, headers=headers, json=data)

        if response.status_code not in [200, 201]:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error updating record: HTTP %d - %s" % (
                    self.description, response.status_code, response.text
                )
            )
            return False

        # Check if there's an action to wait for
        try:
            response_data = response.json()
            action = response_data.get('action', {})
            action_id = action.get('id')

            if action_id and action.get('status') in ['running', 'pending']:
                if not self._wait_for_action(headers, action_id):
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s update action failed or timed out for %s %s" % (
                            self.description, record_name, record_type
                        )
                    )
                    return False
        except Exception:
            pass  # No action in response, that's fine

        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s updated %s %s with %s" % (
                    self.description, record_name, record_type, address
                )
            )

        return True

    def _create_record(self, headers, zone_id, record_name, record_type, address):
        """Create new record with async action handling."""
        url = f"{self._api_base}/zones/{zone_id}/rrsets"

        data = {
            'name': record_name,
            'type': record_type,
            'records': [{'value': str(address)}],
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

        # Check if there's an action to wait for
        try:
            response_data = response.json()
            action = response_data.get('action', {})
            action_id = action.get('id')

            if action_id and action.get('status') in ['running', 'pending']:
                if not self._wait_for_action(headers, action_id):
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s create action failed or timed out for %s %s" % (
                            self.description, record_name, record_type
                        )
                    )
                    return False
        except Exception:
            pass  # No action in response, that's fine

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
                existing = self._get_record(headers, zone_id, record_name, record_type)

                if existing:
                    success = self._update_record(
                        headers, zone_id, record_name, record_type, self.current_address
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
