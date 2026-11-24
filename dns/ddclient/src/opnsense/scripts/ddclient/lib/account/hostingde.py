"""
    Copyright (c) 2026 Christian Schäfer
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
import ipaddress
import syslog
import requests
from . import BaseAccount

# hosting.de DNS API documentation: https://www.hosting.de/api/
#
# Only existing A/AAAA records are updated (in place, via recordsUpdate/recordsToModify), records are never created.
# The API key (hosting.de control panel) is entered as password, the zone containing the hostname(s) as zone.
# Hostnames are expected as fqdn (www.example.com), single label names (www) and the zone itself (@) are accepted too.
# All data received from the API is treated as untrusted, it is only compared, logged (sanitized) or sent back as json.


class HostingDe(BaseAccount):
    _priority = 65535

    _api_base = 'https://secure.hosting.de/api/dns/v1/json'
    # record fields which are returned by the API, but not accepted as input
    _out_only_fields = ['zoneId', 'zoneConfigId', 'lastChangeDate']

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return {'hostingde': 'hosting.de'}

    @staticmethod
    def match(account):
        return account.get('service') == 'hostingde'

    def _api(self, method, payload):
        """ call hosting.de API, returns the decoded response when status is success/pending, otherwise None
        """
        payload = dict(payload, authToken=self.settings.get('password', ''))
        try:
            req = requests.post(
                '%s/%s' % (self._api_base, method),
                json=payload,
                headers={'User-Agent': 'OPNsense-dyndns', 'Accept': 'application/json'},
                timeout=30
            )
            data = req.json()
        except (requests.exceptions.RequestException, ValueError) as e:
            syslog.syslog(syslog.LOG_ERR, "Account %s %s request failed: %s" % (self.description, method, e))
            return None

        if not isinstance(data, dict):
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s %s failed [HTTP %d - unexpected response]" % (self.description, method, req.status_code)
            )
            return None

        if req.status_code != 200 or data.get('status') not in ['success', 'pending']:
            errors = data.get('errors', [])
            errors = "; ".join(
                "%s %s" % (err.get('code'), err.get('text')) for err in errors if isinstance(err, dict)
            ) if isinstance(errors, list) else ''
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s %s failed [HTTP %d - %s %s]" % (
                    self.description, method, req.status_code, self._clean(data.get('status')), self._clean(errors)
                )
            )
            return None
        return data

    @staticmethod
    def _clean(value, maxlen=200):
        """ received data is untrusted, never log it unfiltered (no control characters, limited length)
        """
        return ''.join(c if c.isprintable() else ' ' for c in str(value))[:maxlen]

    @staticmethod
    def _fqdn(hostname, zone):
        """ hostnames are normally entered as fqdn (like most providers), @ and single label names are expanded.
            returns None when the hostname doesn't belong to the zone
        """
        hostname = hostname.strip().rstrip('.')
        if hostname in ['', '@'] or hostname.lower() == zone.lower():
            return zone
        if hostname.lower().endswith('.' + zone.lower()):
            return hostname
        if '.' not in hostname:
            return '%s.%s' % (hostname, zone)
        return None

    @staticmethod
    def _same_address(a, b):
        try:
            return ipaddress.ip_address(str(a).strip()) == ipaddress.ip_address(str(b).strip())
        except ValueError:
            return str(a) == str(b)

    @staticmethod
    def _filter(**fields):
        return {
            'subFilterConnective': 'AND',
            'subFilter': [{'field': key, 'value': value} for key, value in fields.items()]
        }

    def _zone_config_id(self, zone):
        data = self._api('zoneConfigsFind', {'filter': {'field': 'zoneName', 'value': zone}, 'limit': 10})
        if data is None:
            return None
        for item in data.get('response', {}).get('data', []):
            if str(item.get('name', '')).lower() == zone.lower() and item.get('id'):
                return item['id']
        syslog.syslog(syslog.LOG_ERR, "Account %s zone '%s' not found" % (self.description, zone))
        return None

    def _update_host(self, zone_id, hostname, record_type):
        """ modify the existing record in place, returns True when the record is up to date afterwards
        """
        data = self._api('recordsFind', {
            'filter': self._filter(zoneConfigId=zone_id, recordName=hostname, recordType=record_type),
            'limit': 10
        })
        if data is None:
            return False
        records = [
            r for r in data.get('response', {}).get('data', [])
            if str(r.get('name', '')).lower() == hostname.lower() and r.get('type') == record_type
        ]
        if len(records) != 1:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s expected exactly one existing %s record for %s, found %d "
                "(records are never created, add the record in hosting.de first)" % (
                    self.description, record_type, hostname, len(records)
                )
            )
            return False

        record = records[0]
        if not self._same_address(record.get('content'), self.current_address):
            # keep all other (in) fields of the existing record, only the content changes
            modified = {k: v for k, v in record.items() if k not in self._out_only_fields}
            modified['content'] = str(self.current_address)
            if self._api('recordsUpdate', {
                'zoneConfigId': zone_id,
                'recordsToAdd': [],
                'recordsToModify': [modified],
                'recordsToDelete': []
            }) is None:
                return False
        return True

    def execute(self):
        if super().execute():
            zone = self.settings.get('zone', '').strip().rstrip('.')
            hostnames = [h.strip() for h in self.settings.get('hostnames', '').split(',') if h.strip()]
            if not zone or not hostnames:
                syslog.syslog(syslog.LOG_ERR, "Account %s zone and hostname(s) are required" % self.description)
                return False

            record_type = 'AAAA' if ':' in str(self.current_address) else 'A'
            zone_id = self._zone_config_id(zone)
            if zone_id is None:
                return False

            all_good = True
            for hostname in hostnames:
                fqdn = self._fqdn(hostname, zone)
                if fqdn is None:
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s hostname %s is not part of zone %s" % (self.description, hostname, zone)
                    )
                    all_good = False
                    continue
                hostname = fqdn
                if self._update_host(zone_id, hostname, record_type):
                    if self.is_verbose:
                        syslog.syslog(
                            syslog.LOG_NOTICE,
                            "Account %s set new ip %s for %s" % (self.description, self.current_address, hostname)
                        )
                else:
                    all_good = False

            if all_good:
                self.update_state(address=self.current_address)
                return True
        return False
