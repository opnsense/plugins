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

    Shared Hetzner DNS API library - used by both ddclient providers and HCloudDNS
"""
import hashlib
import syslog
import requests

TIMEOUT = 15


class HetznerAPIError(Exception):
    """Custom exception for Hetzner API errors"""

    def __init__(self, message, status_code=None, response_body=None):
        super().__init__(message)
        self.status_code = status_code
        self.response_body = response_body


class HetznerCloudAPI:
    """
    Hetzner Cloud DNS API (api.hetzner.cloud)
    Uses Bearer token authentication and rrsets endpoints
    """

    _api_base = "https://api.hetzner.cloud/v1"

    def __init__(self, token, verbose=False):
        self.token = token
        self.verbose = verbose
        self.headers = {
            'User-Agent': 'OPNsense-HCloudDNS/2.0',
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }

    def _log(self, level, message):
        """Log message to syslog"""
        syslog.syslog(level, f"HCloudDNS: {message}")

    def _request(self, method, endpoint, params=None, json_data=None):
        """Make API request with error handling"""
        url = f"{self._api_base}{endpoint}"

        try:
            response = requests.request(
                method=method,
                url=url,
                headers=self.headers,
                params=params,
                json=json_data,
                timeout=TIMEOUT
            )

            if self.verbose:
                self._log(syslog.LOG_DEBUG, f"{method} {endpoint} -> {response.status_code}")

            return response

        except requests.exceptions.Timeout:
            raise HetznerAPIError("API request timed out")
        except requests.exceptions.ConnectionError:
            raise HetznerAPIError("Failed to connect to Hetzner Cloud API")
        except requests.exceptions.RequestException as e:
            raise HetznerAPIError(f"API request failed: {str(e)}")

    def validate_token(self):
        """
        Validate token by attempting to list zones.
        Returns tuple (valid: bool, message: str, zone_count: int)
        """
        try:
            response = self._request('GET', '/zones')

            if response.status_code == 401:
                return False, "Invalid API token", 0

            if response.status_code == 403:
                return False, "API token lacks required permissions", 0

            if response.status_code != 200:
                return False, f"API error: HTTP {response.status_code}", 0

            data = response.json()
            zones = data.get('zones', [])
            zone_count = len(zones)

            return True, f"Token valid - {zone_count} zone(s) found", zone_count

        except HetznerAPIError as e:
            return False, str(e), 0
        except Exception as e:
            return False, f"Unexpected error: {str(e)}", 0

    def list_zones(self):
        """
        List all DNS zones accessible with this token.
        Returns list of zone dicts with id, name, records_count
        """
        try:
            response = self._request('GET', '/zones')

            if response.status_code != 200:
                self._log(syslog.LOG_ERR, f"Failed to list zones: HTTP {response.status_code}")
                return []

            data = response.json()
            zones = data.get('zones', [])

            result = []
            for zone in zones:
                result.append({
                    'id': zone.get('id', ''),
                    'name': zone.get('name', ''),
                    'records_count': zone.get('records_count', 0),
                    'status': zone.get('status', 'unknown')
                })

            if self.verbose:
                self._log(syslog.LOG_INFO, f"Found {len(result)} zones")

            return result

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to list zones: {str(e)}")
            return []

    def get_zone_id(self, zone_name):
        """Get zone ID by zone name"""
        try:
            response = self._request('GET', '/zones', params={'name': zone_name})

            if response.status_code != 200:
                self._log(syslog.LOG_ERR, f"Failed to get zone: HTTP {response.status_code}")
                return None

            data = response.json()
            zones = data.get('zones', [])

            if not zones:
                self._log(syslog.LOG_ERR, f"Zone '{zone_name}' not found")
                return None

            zone_id = zones[0].get('id')
            if self.verbose:
                self._log(syslog.LOG_INFO, f"Found zone ID {zone_id} for {zone_name}")

            return zone_id

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to get zone: {str(e)}")
            return None

    def list_records(self, zone_id, record_types=None):
        """
        List DNS records for a zone.
        Filters to A and AAAA records by default.
        Handles pagination to fetch all records.
        """
        if record_types is None:
            record_types = ['A', 'AAAA']

        try:
            all_rrsets = []
            page = 1
            per_page = 100  # Max allowed by Hetzner API

            # Fetch all pages
            while True:
                response = self._request(
                    'GET',
                    f'/zones/{zone_id}/rrsets',
                    params={'page': page, 'per_page': per_page}
                )

                if response.status_code == 404:
                    self._log(syslog.LOG_ERR, f"Zone {zone_id} not found")
                    return []

                if response.status_code != 200:
                    self._log(syslog.LOG_ERR, f"Failed to list records: HTTP {response.status_code}")
                    return []

                data = response.json()
                rrsets = data.get('rrsets', [])
                all_rrsets.extend(rrsets)

                # Check if there are more pages
                meta = data.get('meta', {}).get('pagination', {})
                total_entries = meta.get('total_entries', len(rrsets))
                last_page = meta.get('last_page', 1)

                if self.verbose:
                    self._log(syslog.LOG_DEBUG, f"Page {page}/{last_page}: {len(rrsets)} rrsets")

                if page >= last_page or len(rrsets) == 0:
                    break

                page += 1

            result = []
            for rrset in all_rrsets:
                if rrset.get('type') in record_types:
                    records = rrset.get('records', [])
                    rrset_name = rrset.get('name', '')
                    rrset_type = rrset.get('type', '')
                    rrset_ttl = rrset.get('ttl', 300)

                    # Create one entry per record value (important for MX, NS, etc.)
                    for record in records:
                        value = record.get('value', '')
                        # Generate synthetic ID from name+type+value
                        record_id = hashlib.md5(f"{rrset_name}:{rrset_type}:{value}".encode()).hexdigest()[:12]
                        result.append({
                            'id': record_id,
                            'name': rrset_name,
                            'type': rrset_type,
                            'value': value,
                            'ttl': rrset_ttl
                        })

            if self.verbose:
                self._log(syslog.LOG_INFO, f"Found {len(result)} records in zone {zone_id} (fetched {len(all_rrsets)} rrsets)")

            return result

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to list records: {str(e)}")
            return []

    def get_record(self, zone_id, name, record_type):
        """Get a specific DNS record by name and type."""
        try:
            response = self._request('GET', f'/zones/{zone_id}/rrsets/{name}/{record_type}')

            if response.status_code == 404:
                return None

            if response.status_code != 200:
                self._log(syslog.LOG_ERR, f"Failed to get record: HTTP {response.status_code}")
                return None

            data = response.json()
            rrset = data.get('rrset', {})

            records = rrset.get('records', [])
            value = records[0].get('value', '') if records else ''

            return {
                'name': rrset.get('name', ''),
                'type': rrset.get('type', ''),
                'value': value,
                'ttl': rrset.get('ttl', 300)
            }

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to get record: {str(e)}")
            return None

    def update_record(self, zone_id, name, record_type, value, ttl=300):
        """
        Update existing record with new value.
        Returns tuple (success: bool, message: str)

        NOTE: Hetzner Cloud API has a bug where PUT returns 200 but doesn't update.
        Workaround: DELETE old record, then POST new record.
        """
        try:
            # Check if record exists
            existing = self.get_record(zone_id, name, record_type)

            if not existing:
                # Record doesn't exist, create it
                return self.create_record(zone_id, name, record_type, value, ttl)

            # Check if value AND ttl are same - no update needed
            if existing.get('value') == str(value) and existing.get('ttl') == ttl:
                return True, "unchanged"

            # Workaround for Cloud API PUT bug: DELETE then POST
            # DELETE the old record
            delete_response = self._request(
                'DELETE', f'/zones/{zone_id}/rrsets/{name}/{record_type}'
            )

            if delete_response.status_code not in [200, 201, 204]:
                error_msg = f"DELETE failed: HTTP {delete_response.status_code}"
                self._log(syslog.LOG_ERR, f"Failed to update {name} {record_type}: {error_msg}")
                return False, error_msg

            # POST new record
            return self.create_record(zone_id, name, record_type, value, ttl)

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to update record: {str(e)}")
            return False, str(e)

    def create_record(self, zone_id, name, record_type, value, ttl=300):
        """
        Create new DNS record.
        Returns tuple (success: bool, message: str)
        """
        try:
            url = f'/zones/{zone_id}/rrsets'
            data = {
                'name': name,
                'type': record_type,
                'records': [{'value': str(value)}],
                'ttl': ttl
            }

            response = self._request('POST', url, json_data=data)

            if response.status_code in [200, 201]:
                if self.verbose:
                    self._log(syslog.LOG_INFO, f"Created {name} {record_type} -> {value}")
                return True, f"Created {name} {record_type}"

            error_msg = f"HTTP {response.status_code}"
            try:
                error_data = response.json()
                if 'error' in error_data:
                    error_msg = error_data['error'].get('message', error_msg)
            except Exception:
                pass

            self._log(syslog.LOG_ERR, f"Failed to create {name} {record_type}: {error_msg}")
            return False, error_msg

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to create record: {str(e)}")
            return False, str(e)

    def delete_record(self, zone_id, name, record_type):
        """
        Delete a DNS record.
        Returns tuple (success: bool, message: str)
        """
        try:
            response = self._request('DELETE', f'/zones/{zone_id}/rrsets/{name}/{record_type}')

            if response.status_code in [200, 201, 204]:
                if self.verbose:
                    self._log(syslog.LOG_INFO, f"Deleted {name} {record_type}")
                return True, f"Deleted {name} {record_type}"

            if response.status_code == 404:
                return True, "Record not found (already deleted)"

            error_msg = f"HTTP {response.status_code}"
            self._log(syslog.LOG_ERR, f"Failed to delete {name} {record_type}: {error_msg}")
            return False, error_msg

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to delete record: {str(e)}")
            return False, str(e)


class HetznerLegacyAPI:
    """
    Hetzner DNS Console API (dns.hetzner.com)
    Uses Auth-API-Token authentication and /records endpoints
    Will be deprecated May 2026
    """

    _api_base = "https://dns.hetzner.com/api/v1"

    def __init__(self, token, verbose=False):
        self.token = token
        self.verbose = verbose
        self.headers = {
            'User-Agent': 'OPNsense-HCloudDNS/2.0',
            'Auth-API-Token': token,
            'Content-Type': 'application/json'
        }

    def _log(self, level, message):
        """Log message to syslog"""
        syslog.syslog(level, f"HCloudDNS: {message}")

    def _request(self, method, endpoint, params=None, json_data=None):
        """Make API request with error handling"""
        url = f"{self._api_base}{endpoint}"

        try:
            response = requests.request(
                method=method,
                url=url,
                headers=self.headers,
                params=params,
                json=json_data,
                timeout=TIMEOUT
            )

            if self.verbose:
                self._log(syslog.LOG_DEBUG, f"{method} {endpoint} -> {response.status_code}")

            return response

        except requests.exceptions.Timeout:
            raise HetznerAPIError("API request timed out")
        except requests.exceptions.ConnectionError:
            raise HetznerAPIError("Failed to connect to Hetzner DNS API")
        except requests.exceptions.RequestException as e:
            raise HetznerAPIError(f"API request failed: {str(e)}")

    def validate_token(self):
        """
        Validate token by attempting to list zones.
        Returns tuple (valid: bool, message: str, zone_count: int)
        """
        try:
            response = self._request('GET', '/zones')

            if response.status_code == 401:
                return False, "Invalid API token", 0

            if response.status_code == 403:
                return False, "API token lacks required permissions", 0

            if response.status_code != 200:
                return False, f"API error: HTTP {response.status_code}", 0

            data = response.json()
            zones = data.get('zones', [])
            zone_count = len(zones)

            return True, f"Token valid - {zone_count} zone(s) found", zone_count

        except HetznerAPIError as e:
            return False, str(e), 0
        except Exception as e:
            return False, f"Unexpected error: {str(e)}", 0

    def list_zones(self):
        """List all DNS zones accessible with this token."""
        try:
            response = self._request('GET', '/zones')

            if response.status_code != 200:
                self._log(syslog.LOG_ERR, f"Failed to list zones: HTTP {response.status_code}")
                return []

            data = response.json()
            zones = data.get('zones', [])

            result = []
            for zone in zones:
                result.append({
                    'id': zone.get('id', ''),
                    'name': zone.get('name', ''),
                    'records_count': zone.get('records_count', 0),
                    'status': zone.get('status', 'unknown')
                })

            if self.verbose:
                self._log(syslog.LOG_INFO, f"Found {len(result)} zones")

            return result

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to list zones: {str(e)}")
            return []

    def get_zone_id(self, zone_name):
        """Get zone ID by zone name"""
        try:
            response = self._request('GET', '/zones')

            if response.status_code != 200:
                self._log(syslog.LOG_ERR, f"Failed to get zones: HTTP {response.status_code}")
                return None

            data = response.json()
            zones = data.get('zones', [])

            for zone in zones:
                if zone.get('name') == zone_name:
                    zone_id = zone.get('id')
                    if self.verbose:
                        self._log(syslog.LOG_INFO, f"Found zone ID {zone_id} for {zone_name}")
                    return zone_id

            self._log(syslog.LOG_ERR, f"Zone '{zone_name}' not found")
            return None

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to get zone: {str(e)}")
            return None

    def list_records(self, zone_id, record_types=None):
        """List DNS records for a zone. Handles pagination to fetch all records."""
        if record_types is None:
            record_types = ['A', 'AAAA']

        try:
            all_records = []
            page = 1
            per_page = 100  # Max allowed by Hetzner API

            # Fetch all pages
            while True:
                response = self._request(
                    'GET',
                    '/records',
                    params={'zone_id': zone_id, 'page': page, 'per_page': per_page}
                )

                if response.status_code != 200:
                    self._log(syslog.LOG_ERR, f"Failed to list records: HTTP {response.status_code}")
                    return []

                data = response.json()
                records = data.get('records', [])
                all_records.extend(records)

                # Check if there are more pages (Legacy API uses meta.pagination)
                meta = data.get('meta', {}).get('pagination', {})
                last_page = meta.get('last_page', 1)

                if self.verbose:
                    self._log(syslog.LOG_DEBUG, f"Page {page}/{last_page}: {len(records)} records")

                if page >= last_page or len(records) == 0:
                    break

                page += 1

            result = []
            for record in all_records:
                if record.get('type') in record_types:
                    result.append({
                        'id': record.get('id', ''),
                        'name': record.get('name', ''),
                        'type': record.get('type', ''),
                        'value': record.get('value', ''),
                        'ttl': record.get('ttl', 300)
                    })

            if self.verbose:
                self._log(syslog.LOG_INFO, f"Found {len(result)} records in zone {zone_id} (fetched {len(all_records)} total)")

            return result

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to list records: {str(e)}")
            return []

    def get_record(self, zone_id, name, record_type):
        """Get a specific DNS record by name and type."""
        records = self.list_records(zone_id, [record_type])

        for record in records:
            if record.get('name') == name and record.get('type') == record_type:
                return record

        return None

    def _get_record_id(self, zone_id, name, record_type):
        """Get record ID by name and type"""
        record = self.get_record(zone_id, name, record_type)
        return record.get('id') if record else None

    def update_record(self, zone_id, name, record_type, value, ttl=300):
        """
        Update or create a DNS record.
        Returns tuple (success: bool, message: str)
        """
        try:
            record_id = self._get_record_id(zone_id, name, record_type)

            if record_id:
                # Update existing record
                url = f'/records/{record_id}'
                data = {
                    'zone_id': zone_id,
                    'type': record_type,
                    'name': name,
                    'value': str(value),
                    'ttl': ttl
                }

                response = self._request('PUT', url, json_data=data)

                if response.status_code == 200:
                    if self.verbose:
                        self._log(syslog.LOG_INFO, f"Updated {name} {record_type} -> {value}")
                    return True, f"Updated {name} {record_type}"

                error_msg = f"HTTP {response.status_code}"
                self._log(syslog.LOG_ERR, f"Failed to update {name} {record_type}: {error_msg}")
                return False, error_msg
            else:
                # Create new record
                return self.create_record(zone_id, name, record_type, value, ttl)

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to update record: {str(e)}")
            return False, str(e)

    def create_record(self, zone_id, name, record_type, value, ttl=300):
        """
        Create new DNS record.
        Returns tuple (success: bool, message: str)
        """
        try:
            url = '/records'
            data = {
                'zone_id': zone_id,
                'type': record_type,
                'name': name,
                'value': str(value),
                'ttl': ttl
            }

            response = self._request('POST', url, json_data=data)

            if response.status_code in [200, 201]:
                if self.verbose:
                    self._log(syslog.LOG_INFO, f"Created {name} {record_type} -> {value}")
                return True, f"Created {name} {record_type}"

            error_msg = f"HTTP {response.status_code}"
            self._log(syslog.LOG_ERR, f"Failed to create {name} {record_type}: {error_msg}")
            return False, error_msg

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to create record: {str(e)}")
            return False, str(e)

    def delete_record(self, zone_id, name, record_type):
        """
        Delete a DNS record.
        Returns tuple (success: bool, message: str)
        """
        try:
            record_id = self._get_record_id(zone_id, name, record_type)

            if not record_id:
                return True, "Record not found (already deleted)"

            response = self._request('DELETE', f'/records/{record_id}')

            if response.status_code in [200, 204]:
                if self.verbose:
                    self._log(syslog.LOG_INFO, f"Deleted {name} {record_type}")
                return True, f"Deleted {name} {record_type}"

            error_msg = f"HTTP {response.status_code}"
            self._log(syslog.LOG_ERR, f"Failed to delete {name} {record_type}: {error_msg}")
            return False, error_msg

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to delete record: {str(e)}")
            return False, str(e)


def create_api(token, api_type='cloud', verbose=False):
    """
    Factory function to create the appropriate API instance.
    api_type: 'cloud' for api.hetzner.cloud, 'dns' for dns.hetzner.com
    """
    if api_type == 'dns':
        return HetznerLegacyAPI(token, verbose)
    return HetznerCloudAPI(token, verbose)
