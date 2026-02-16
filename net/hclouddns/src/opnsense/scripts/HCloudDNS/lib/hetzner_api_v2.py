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

    Enhanced Hetzner Cloud DNS API v2 with rate limiting and retry logic.
    Cloud API only (api.hetzner.cloud) - Legacy API not supported.
"""
import hashlib
import syslog
import threading
import time
import requests

from hetzner_api import HetznerAPIError

TIMEOUT = 15
ACTION_POLL_INTERVAL = 1.0  # seconds between action status polls (up from 0.5s in v1)
ACTION_MAX_WAIT = 30
MAX_RETRIES = 3


class TokenBucket:
    """
    Thread-safe token bucket rate limiter.
    Allows bursts up to burst_size, refills at tokens_per_second.
    """

    def __init__(self, tokens_per_second=5, burst_size=10):
        self.tokens_per_second = tokens_per_second
        self.burst_size = burst_size
        self._tokens = float(burst_size)
        self._last_refill = time.monotonic()
        self._lock = threading.Lock()

    def acquire(self):
        """
        Acquire a token, blocking if none available.
        Sleep is done outside the lock to avoid holding it during waits.
        """
        while True:
            with self._lock:
                now = time.monotonic()
                elapsed = now - self._last_refill
                self._tokens = min(
                    self.burst_size,
                    self._tokens + elapsed * self.tokens_per_second
                )
                self._last_refill = now

                if self._tokens >= 1.0:
                    self._tokens -= 1.0
                    return

                # Calculate wait time for next token
                wait_time = (1.0 - self._tokens) / self.tokens_per_second

            time.sleep(wait_time)


class HetznerCloudAPIv2:
    """
    Enhanced Hetzner Cloud DNS API with rate limiting and 429 retry.
    Primary Cloud DNS API implementation with rate limiting and retry.
    Cloud API only (api.hetzner.cloud).
    """

    _api_base = "https://api.hetzner.cloud/v1"

    def __init__(self, token, verbose=False):
        self.token = token
        self.verbose = verbose
        self.headers = {
            'User-Agent': 'OPNsense-HCloudDNS/2.1',
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }
        self._rate_limiter = TokenBucket(tokens_per_second=5, burst_size=10)

    def _log(self, level, message):
        syslog.syslog(level, f"HCloudDNS: {message}")

    def _request(self, method, endpoint, params=None, json_data=None):
        """Make API request with rate limiting and 429 retry."""
        url = f"{self._api_base}{endpoint}"

        for attempt in range(MAX_RETRIES + 1):
            # Acquire rate limit token before each request
            self._rate_limiter.acquire()

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
                    self._log(syslog.LOG_DEBUG, f"v2: {method} {endpoint} -> {response.status_code}")

                if response.status_code == 429:
                    if attempt < MAX_RETRIES:
                        # Use Retry-After header if available, else exponential backoff
                        retry_after = response.headers.get('Retry-After')
                        if retry_after:
                            try:
                                wait = float(retry_after)
                            except ValueError:
                                wait = 2 ** attempt
                        else:
                            wait = 2 ** attempt
                        self._log(
                            syslog.LOG_WARNING,
                            f"v2: Rate limited (429), retrying in {wait}s (attempt {attempt + 1}/{MAX_RETRIES})"
                        )
                        time.sleep(wait)
                        continue
                    else:
                        raise HetznerAPIError(
                            "Rate limited after max retries",
                            status_code=429
                        )

                return response

            except requests.exceptions.Timeout:
                if attempt < MAX_RETRIES:
                    self._log(syslog.LOG_WARNING, f"v2: Timeout, retrying (attempt {attempt + 1}/{MAX_RETRIES})")
                    time.sleep(2 ** attempt)
                    continue
                raise HetznerAPIError("API request timed out after retries")
            except requests.exceptions.ConnectionError:
                if attempt < MAX_RETRIES:
                    self._log(syslog.LOG_WARNING, f"v2: Connection error, retrying (attempt {attempt + 1}/{MAX_RETRIES})")
                    time.sleep(2 ** attempt)
                    continue
                raise HetznerAPIError("Failed to connect to Hetzner Cloud API after retries")
            except requests.exceptions.RequestException as e:
                raise HetznerAPIError(f"API request failed: {str(e)}")

        raise HetznerAPIError("Max retries exceeded")

    def _wait_for_action(self, action_id):
        """Wait for an async action to complete with 1s polling."""
        start_time = time.time()

        while time.time() - start_time < ACTION_MAX_WAIT:
            try:
                response = self._request('GET', f'/actions/{action_id}')

                if response.status_code != 200:
                    return False, f"Failed to get action status: HTTP {response.status_code}"

                data = response.json()
                action = data.get('action', {})
                status = action.get('status', '')

                if status == 'success':
                    return True, "Action completed successfully"
                elif status == 'error':
                    error = action.get('error', {})
                    error_msg = error.get('message', 'Unknown error')
                    return False, f"Action failed: {error_msg}"
                elif status in ['running', 'pending']:
                    time.sleep(ACTION_POLL_INTERVAL)
                    continue
                else:
                    return True, f"Action status: {status}"

            except HetznerAPIError as e:
                return False, f"Error waiting for action: {str(e)}"

        return False, f"Action timed out after {ACTION_MAX_WAIT} seconds"

    def _handle_action_response(self, response_data, context=""):
        """Check response for async action and wait for completion."""
        action = response_data.get('action', {})
        action_id = action.get('id')

        if action_id and action.get('status') in ['running', 'pending']:
            success, msg = self._wait_for_action(action_id)
            if not success:
                self._log(syslog.LOG_ERR, f"Action failed{' for ' + context if context else ''}: {msg}")
                return False, msg

        return True, "OK"

    def validate_token(self):
        """Validate token by listing zones. Returns (valid, message, zone_count)."""
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
            return True, f"Token valid - {len(zones)} zone(s) found", len(zones)

        except HetznerAPIError as e:
            return False, str(e), 0
        except Exception as e:
            return False, f"Unexpected error: {str(e)}", 0

    def list_zones(self):
        """List all DNS zones with pagination."""
        try:
            all_zones = []
            page = 1
            per_page = 100

            while True:
                response = self._request('GET', '/zones', params={'page': page, 'per_page': per_page})
                if response.status_code != 200:
                    self._log(syslog.LOG_ERR, f"Failed to list zones: HTTP {response.status_code}")
                    return []

                data = response.json()
                zones = data.get('zones', [])
                all_zones.extend(zones)

                meta = data.get('meta', {}).get('pagination', {})
                total_entries = meta.get('total_entries', len(zones))
                if len(all_zones) >= total_entries or len(zones) < per_page:
                    break
                page += 1

            result = []
            for zone in all_zones:
                result.append({
                    'id': zone.get('id', ''),
                    'name': zone.get('name', ''),
                    'records_count': zone.get('records_count', 0),
                    'status': zone.get('status', 'unknown')
                })

            if self.verbose:
                self._log(syslog.LOG_INFO, f"v2: Found {len(result)} zones")
            return result

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to list zones: {str(e)}")
            return []

    def get_zone_id(self, zone_name):
        """Get zone ID by zone name."""
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
                self._log(syslog.LOG_INFO, f"v2: Found zone ID {zone_id} for {zone_name}")
            return zone_id

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to get zone: {str(e)}")
            return None

    def list_records(self, zone_id, record_types=None):
        """List DNS records for a zone with pagination."""
        if record_types is None:
            record_types = ['A', 'AAAA']

        try:
            all_rrsets = []
            page = 1
            per_page = 100

            while True:
                response = self._request(
                    'GET', f'/zones/{zone_id}/rrsets',
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

                meta = data.get('meta', {}).get('pagination', {})
                last_page = meta.get('last_page', 1)

                if self.verbose:
                    self._log(syslog.LOG_DEBUG, f"v2: Page {page}/{last_page}: {len(rrsets)} rrsets")

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

                    for record in records:
                        value = record.get('value', '')
                        record_id = hashlib.md5(f"{rrset_name}:{rrset_type}:{value}".encode()).hexdigest()[:12]
                        result.append({
                            'id': record_id,
                            'name': rrset_name,
                            'type': rrset_type,
                            'value': value,
                            'ttl': rrset_ttl
                        })

            if self.verbose:
                self._log(syslog.LOG_INFO, f"v2: Found {len(result)} records in zone {zone_id}")
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

    def _parse_error(self, response):
        """Extract error message from API response."""
        error_msg = f"HTTP {response.status_code}"
        try:
            error_data = response.json()
            if 'error' in error_data:
                error_msg = error_data['error'].get('message', error_msg)
        except Exception:
            pass
        return error_msg

    def change_ttl(self, zone_id, name, record_type, ttl):
        """
        Change TTL of an RRset using the dedicated change_ttl action endpoint.
        Returns tuple (success: bool, message: str)
        """
        try:
            url = f'/zones/{zone_id}/rrsets/{name}/{record_type}/actions/change_ttl'
            response = self._request('POST', url, json_data={'ttl': ttl})

            if response.status_code in [200, 201]:
                success, msg = self._handle_action_response(
                    response.json(), f"{name} {record_type} TTL"
                )
                if not success:
                    return False, msg
                if self.verbose:
                    self._log(syslog.LOG_INFO, f"v2: Changed TTL for {name} {record_type} -> {ttl}")
                return True, f"TTL updated to {ttl}"

            error_msg = self._parse_error(response)
            self._log(syslog.LOG_ERR, f"v2: Failed to change TTL for {name} {record_type}: {error_msg}")
            return False, error_msg

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"v2: Failed to change TTL: {str(e)}")
            return False, str(e)

    def update_record(self, zone_id, name, record_type, value, ttl=300):
        """
        Update existing record value and/or TTL.
        Returns tuple (success: bool, message: str)

        Uses set_records for value changes and change_ttl for TTL changes,
        as per Hetzner Cloud API requirements (separate endpoints).
        """
        try:
            existing = self.get_record(zone_id, name, record_type)

            if not existing:
                return self.create_record(zone_id, name, record_type, value, ttl)

            value_changed = existing.get('value') != str(value)
            ttl_changed = existing.get('ttl') != ttl

            if not value_changed and not ttl_changed:
                return True, "unchanged"

            # Update value via set_records if changed
            if value_changed:
                url = f'/zones/{zone_id}/rrsets/{name}/{record_type}/actions/set_records'
                response = self._request('POST', url, json_data={'records': [{'value': str(value)}]})

                if response.status_code not in [200, 201]:
                    error_msg = self._parse_error(response)
                    self._log(syslog.LOG_ERR, f"v2: Failed to update {name} {record_type}: {error_msg}")
                    return False, error_msg

                success, msg = self._handle_action_response(
                    response.json(), f"{name} {record_type}"
                )
                if not success:
                    return False, msg

                if self.verbose:
                    self._log(syslog.LOG_INFO, f"v2: Updated {name} {record_type} -> {value}")

            # Update TTL via change_ttl if changed
            if ttl_changed:
                success, msg = self.change_ttl(zone_id, name, record_type, ttl)
                if not success:
                    return False, msg

            return True, f"Updated {name} {record_type}"

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"v2: Failed to update record: {str(e)}")
            return False, str(e)

    def create_record(self, zone_id, name, record_type, value, ttl=300):
        """Create new DNS record. Returns (success, message)."""
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
                try:
                    response_data = response.json()
                    success, msg = self._handle_action_response(
                        response_data, f"{name} {record_type}"
                    )
                    if not success:
                        return False, msg
                except Exception:
                    pass

                if self.verbose:
                    self._log(syslog.LOG_INFO, f"v2: Created {name} {record_type} -> {value}")
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
        """Delete a DNS record. Returns (success, message)."""
        try:
            response = self._request('DELETE', f'/zones/{zone_id}/rrsets/{name}/{record_type}')

            if response.status_code in [200, 201, 204]:
                try:
                    response_data = response.json()
                    success, msg = self._handle_action_response(
                        response_data, f"{name} {record_type}"
                    )
                    if not success:
                        return False, msg
                except Exception:
                    pass

                if self.verbose:
                    self._log(syslog.LOG_INFO, f"v2: Deleted {name} {record_type}")
                return True, f"Deleted {name} {record_type}"

            if response.status_code == 404:
                return True, "Record not found (already deleted)"

            error_msg = f"HTTP {response.status_code}"
            self._log(syslog.LOG_ERR, f"Failed to delete {name} {record_type}: {error_msg}")
            return False, error_msg

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"Failed to delete record: {str(e)}")
            return False, str(e)

    def update_ttl(self, zone_id, name, record_type, ttl):
        """Update only the TTL of an existing record using the dedicated change_ttl endpoint."""
        try:
            existing = self.get_record(zone_id, name, record_type)
            if not existing:
                return False, "Record not found"

            if existing.get('ttl') == ttl:
                return True, "unchanged"

            return self.change_ttl(zone_id, name, record_type, ttl)

        except HetznerAPIError as e:
            self._log(syslog.LOG_ERR, f"v2: Failed to update TTL: {str(e)}")
            return False, str(e)


def create_api_v2(token, verbose=False):
    """Factory function to create a v2 API instance."""
    return HetznerCloudAPIv2(token, verbose)
