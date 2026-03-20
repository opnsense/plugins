"""
    Copyright (c) 2026 Carsten Kallies
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

    all-inkl.com KAS API DynDNS provider for OPNsense ddclient.

    Uses the KAS SOAP API (KasApi.wsdl) to update A/AAAA records.

    API endpoint: https://kasapi.kasserver.com/soap/KasApi.php
    WSDL:         https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl

    UI fields:
      username  - KAS login (all-inkl Benutzername, z.B. "w0xxxxx")
      password  - KAS Passwort (Klartext, wird über HTTPS übertragen)
      hostnames - FQDN(s) zum Aktualisieren, kommagetrennt (z.B. "example.com,*.example.com")
      zone      - DNS-Zone (z.B. "example.com"); wird aus hostname abgeleitet wenn leer
"""
import json
import re
import syslog
import time

import requests

from . import BaseAccount


class AllInkl(BaseAccount):
    """all-inkl.com DynDNS via KAS SOAP API (KasApi)."""

    _priority = 65535

    _services = {
        'allinkl': 'kasapi.kasserver.com'
    }

    _URL    = 'https://kasapi.kasserver.com/soap/KasApi.php'
    _ACTION = '"urn:xmethodsKasApi#KasApi"'

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return {'allinkl': 'all-inkl.com (KAS API)'}

    @staticmethod
    def match(account):
        return account.get('service') in AllInkl._services

    # ------------------------------------------------------------------
    # SOAP / KAS helpers
    # ------------------------------------------------------------------

    def _build_envelope(self, params_dict):
        """Build a KasApi SOAP envelope. params_dict is JSON-serialised into <Params>."""
        params_json = json.dumps(params_dict)
        # Escape XML special characters in the JSON string
        params_json = (params_json
                       .replace('&', '&amp;')
                       .replace('<', '&lt;')
                       .replace('>', '&gt;'))
        return (
            '<?xml version="1.0" encoding="utf-8"?>'
            '<SOAP-ENV:Envelope'
            ' xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
            ' xmlns:ns1="urn:xmethodsKasApi"'
            ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            ' xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"'
            ' SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'
            '<SOAP-ENV:Body>'
            '<ns1:KasApi>'
            '<Params xsi:type="xsd:string">' + params_json + '</Params>'
            '</ns1:KasApi>'
            '</SOAP-ENV:Body>'
            '</SOAP-ENV:Envelope>'
        )

    def _kas_api(self, action, request_params):
        """Execute a KAS API action. Returns response text or None on failure."""
        params = {
            'kas_login':        self.settings.get('username', ''),
            'kas_auth_type':    'plain',
            'kas_auth_data':    self.settings.get('password', ''),
            'kas_action':       action,
            'KasRequestParams': request_params,
        }
        envelope = self._build_envelope(params)

        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s KAS action '%s' params: %s" % (
                    self.description, action, json.dumps(request_params)
                )
            )

        try:
            resp = requests.post(
                self._URL,
                data=envelope.encode('utf-8'),
                headers={
                    'Content-Type': 'text/xml; charset=utf-8',
                    'SOAPAction':   self._ACTION,
                    'User-Agent':   'OPNsense-dyndns',
                },
                timeout=30
            )
        except requests.RequestException as exc:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s KAS request failed: %s" % (self.description, exc)
            )
            return None

        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s KAS '%s' HTTP %d: %s" % (
                    self.description, action, resp.status_code, resp.text[:600]
                )
            )

        if '<SOAP-ENV:Fault>' in resp.text:
            fault = re.search(r'<faultstring>([^<]+)</faultstring>', resp.text)
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s KAS '%s' SOAP fault: %s" % (
                    self.description, action,
                    fault.group(1) if fault else resp.text[:200]
                )
            )
            return None

        return resp.text

    # ------------------------------------------------------------------
    # Response parsing
    # ------------------------------------------------------------------

    def _find_record_id(self, xml_text, record_label, record_type):
        """
        Parse get_dns_settings response and return record_id for the matching
        record_name / record_type, or None.

        The KAS response contains ns2:Map items with key/value pairs:
          <item><key ...>record_name</key><value ...>dyn</value></item>
          <item><key ...>record_type</key><value ...>A</value></item>
          <item><key ...>record_id</key><value ...>12345</value></item>
        """
        def _kv(chunk, key):
            """Extract value for key in a key/value chunk."""
            m = re.search(
                r'<key[^>]*>' + re.escape(key) + r'</key>\s*<value[^>]*>([^<]*)</value>',
                chunk
            )
            return m.group(1) if m else None

        # Split on record boundaries — each record is an <item xsi:type="ns2:Map"> block
        chunks = re.split(r'<item\s[^>]*ns2:Map[^>]*>', xml_text)

        for chunk in chunks:
            r_name = _kv(chunk, 'record_name')
            r_type = _kv(chunk, 'record_type')
            r_id   = _kv(chunk, 'record_id')

            if r_name is None or r_type is None or r_id is None:
                continue

            if r_name == record_label and r_type == record_type:
                return r_id

        return None

    # ------------------------------------------------------------------
    # Zone / label helpers
    # ------------------------------------------------------------------

    def _get_zone(self, hostname):
        """Return the DNS zone for a hostname (from config or derived)."""
        zone = self.settings.get('zone', '').strip().rstrip('.')
        if zone:
            return zone
        parts = hostname.split('.')
        if len(parts) > 2:
            return '.'.join(parts[1:])
        return hostname

    def _get_label(self, hostname, zone):
        """Return the record label (left of zone) for a hostname.

        Examples:
          dyn.example.com  / zone example.com  → 'dyn'
          *.example.com    / zone example.com  → '*'
          example.com      / zone example.com  → ''   (root record)
        """
        if hostname == zone:
            return ''
        if hostname.endswith('.' + zone):
            return hostname[:-len(zone) - 1]
        return hostname.split('.')[0]

    # ------------------------------------------------------------------
    # Main entry point
    # ------------------------------------------------------------------

    def execute(self):
        if not super().execute():
            return False

        record_type = "AAAA" if ':' in str(self.current_address) else "A"

        hostnames_raw = self.settings.get('hostnames', '')
        hostnames = [h.strip() for h in hostnames_raw.split(',') if h.strip()]
        if not hostnames:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s no hostnames configured" % self.description
            )
            return False

        all_success = True
        last_zone   = None
        dns_response = None

        for hostname in hostnames:
            zone      = self._get_zone(hostname)
            zone_host = zone + '.'
            label     = self._get_label(hostname, zone)

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s updating %s (zone: %s, label: '%s', type: %s) → %s" % (
                        self.description, hostname, zone_host,
                        label, record_type, self.current_address
                    )
                )

            # Fetch DNS records once per zone (cache for multiple hostnames in same zone)
            if zone != last_zone:
                dns_response = self._kas_api('get_dns_settings', {'zone_host': zone_host})
                last_zone = zone
                if dns_response is None:
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s failed to retrieve DNS settings for %s" % (
                            self.description, zone_host
                        )
                    )
                    all_success = False
                    continue
                # Respect KasFloodDelay between consecutive API calls
                time.sleep(2)

            record_id = self._find_record_id(dns_response, label, record_type)
            if record_id is None:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s record '%s' type %s not found in zone %s" % (
                        self.description, label, record_type, zone_host
                    )
                )
                all_success = False
                continue

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s found record_id %s for label '%s' %s" % (
                        self.description, record_id, label, record_type
                    )
                )

            update_response = self._kas_api('update_dns_settings', {
                'zone_host':   zone_host,
                'record_id':   record_id,
                'record_name': label,
                'record_type': record_type,
                'record_data': str(self.current_address),
                'record_aux':  '0',
            })

            if update_response is None:
                all_success = False
                continue

            if re.search(r'<value[^>]*>TRUE</value>', update_response, re.IGNORECASE):
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s set new IP %s for %s" % (
                        self.description, self.current_address, hostname
                    )
                )
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s update_dns_settings failed for %s: %s" % (
                        self.description, hostname, update_response[:300]
                    )
                )
                all_success = False

            time.sleep(2)

        if all_success:
            self.update_state(address=self.current_address)
            return True

        return False
