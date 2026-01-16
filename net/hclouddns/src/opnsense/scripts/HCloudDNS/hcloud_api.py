#!/usr/local/bin/python3
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

    Hetzner Cloud API wrapper for HCloudDNS OPNsense plugin
    This is a compatibility wrapper - actual implementation is in lib/hetzner_api.py
"""
import os
import sys

# Add lib directory to path
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), 'lib'))

from hetzner_api import (  # noqa: E402
    HetznerCloudAPI,
    HetznerLegacyAPI,
    HetznerAPIError,
    create_api
)

# Re-export for backward compatibility
HCloudAPIError = HetznerAPIError


class HCloudAPI:
    """
    Backward-compatible wrapper for Hetzner DNS API.
    Delegates to HetznerCloudAPI or HetznerLegacyAPI based on api_type.
    """

    def __init__(self, token, api_type='cloud', verbose=False):
        self._api = create_api(token, api_type, verbose)
        self.api_type = api_type
        self.verbose = verbose

    def validate_token(self):
        return self._api.validate_token()

    def list_zones(self):
        return self._api.list_zones()

    def get_zone_id(self, zone_name):
        return self._api.get_zone_id(zone_name)

    def list_records(self, zone_id, record_types=None):
        return self._api.list_records(zone_id, record_types)

    def get_record(self, zone_id, name, record_type):
        return self._api.get_record(zone_id, name, record_type)

    def update_record(self, zone_id, name, record_type, value, ttl=300):
        return self._api.update_record(zone_id, name, record_type, value, ttl)

    def create_record(self, zone_id, name, record_type, value, ttl=300):
        return self._api.create_record(zone_id, name, record_type, value, ttl)

    def delete_record(self, zone_id, name, record_type):
        return self._api.delete_record(zone_id, name, record_type)


# Export all for convenience
__all__ = [
    'HCloudAPI',
    'HCloudAPIError',
    'HetznerCloudAPI',
    'HetznerLegacyAPI',
    'HetznerAPIError',
    'create_api'
]
