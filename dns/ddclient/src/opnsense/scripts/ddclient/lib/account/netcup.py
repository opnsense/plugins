"""
    Copyright (c) 2023 Ingo Lafrenz <opnsense@der-ingo.de>
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
    ----------------------------------------------------------------------------------------------------
    Netcup DNS provider, see https://ccp.netcup.net/run/webservice/servers/endpoint.php

"""
import syslog
import requests
from . import BaseAccount


class Netcup(BaseAccount):
    _services = ['netcup']

    def __init__(self, account: dict):
        super().__init__(account)
        self.settings['APIPassword'] = None
        self.settings['APIKey'] = None
        # min TTL set to 300
        self.settings['ttl'] = max(int(self.settings['ttl']) if self.settings.get('ttl', '').isdigit() else 0, 300)


    @staticmethod
    def known_services():
        return Netcup._services

    @staticmethod
    def match(account):
        return account.get('service') in Netcup._services

    def execute(self):
        if super().execute():
            if self.settings['hostnames'].find(',') > -1:
                self.settings['hostnames'] = self.settings['hostnames'].split(',')[0]
                syslog.syslog(
                    syslog.LOG_WARNING,
                    "Multiple hostnames detected, ignoring all except first. "+
                    "Consider using CNAMEs or create separate DynDNS instances for each hostname."
                )
            if self.settings['hostnames'].find('.') == -1:
                syslog.syslog(syslog.LOG_ERR, "Incomplete FQDN offerred %s" % self.settings['hostnames'])
                return False

            self.hostname, self.domain = self.settings['hostnames'].split('.', 1)

            if self.settings['password'].count('|') == 1:
                self.settings['APIPassword'], self.settings['APIKey'] = self.settings['password'].split('|')

            if self.settings['APIPassword'] is None or self.settings['APIKey'] is None:
                syslog.syslog(syslog.LOG_ERR, "Unable to parse APIPassword|APIKey.")
                return False

            self.netcupAPISessionID = self._login()
            if not self.netcupAPISessionID:
                return False
            dnsZoneInfo = self._sendRequest(self._createRequestPayload('infoDnsZone'))
            if not dnsZoneInfo:
                return False
            if str(self.settings['ttl']) != dnsZoneInfo['ttl']:
                dnsZoneInfo['ttl'] = str(self.settings['ttl'])
                self._sendRequest(self._createRequestPayload('updateDnsZone', {'dnszone': dnsZoneInfo}))
            dnsRecordsInfo = self._sendRequest(self._createRequestPayload('infoDnsRecords'))
            if not dnsRecordsInfo:
                return False
            recordType = 'AAAA' if ':' in self.current_address else 'A'
            self._updateIpAddress(recordType, dnsRecordsInfo)
            self._logout()
            self.update_state(address=self.current_address)
            return True

    def _login(self):
        requestPayload = {
            'action': 'login',
            'param': {
                'customernumber': self.settings['username'],
                'apikey': self.settings['APIKey'],
                'apipassword': self.settings['APIPassword']
            }
        }
        return self._sendRequest(requestPayload).get('apisessionid', None)

    def _updateDnsRecords(self, hostRecord):
        return self._sendRequest(
            self._createRequestPayload(
                'updateDnsRecords',
                {'dnsrecordset': {'dnsrecords': [hostRecord]}}
            )
        )

    def _logout(self):
        requestPayload = {
            'action': 'logout',
            'param': {
                'customernumber': self._account['username'],
                'apikey': self.settings['APIKey'],
                'apisessionid': self.netcupAPISessionID
            }
        }
        return self._sendRequest(requestPayload)

    def _updateIpAddress(self, recordType, dnsRecordsInfo):
        matchingRecords = [
            r for r in dnsRecordsInfo['dnsrecords'] if r['type'] == recordType and r['hostname'] == self.hostname
        ]
        if len(matchingRecords) > 1:
            raise Exception(f'Too many {recordType} records for hostname {self.hostname} in DNS zone {self.domain}.')
        if matchingRecords:
            hostRecord = matchingRecords[0]
        else:
            hostRecord = {
                'hostname': self.hostname,
                'type': recordType,
                'destination': None
            }
        currentNetcupIPAddress = hostRecord['destination']
        if self.current_address != currentNetcupIPAddress:
            syslog.syslog(
                syslog.LOG_NOTICE,
                f'IP address change detected. Old IP: {currentNetcupIPAddress}, new IP: {self.current_address}'
            )
            hostRecord['destination'] = self.current_address
            if self._updateDnsRecords(hostRecord):
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    f'Successfully updated {recordType} record for {self.hostname}.{self.domain} to {self.current_address}'
                )
        else:
            syslog.syslog(syslog.LOG_NOTICE, 'IP address has not changed. Nothing to do.')

    def _createRequestPayload(self, action, extraParameters={}):
        requestPayload = {
            'action': action,
            'param': {
                'domainname': self.domain,
                'customernumber': self._account['username'],
                'apikey': self.settings['APIKey'],
                'apisessionid': self.netcupAPISessionID,
            }
        }
        requestPayload['param'].update(extraParameters)
        return requestPayload

    def _sendRequest(self, payload):
        req = requests.post(url='https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON', json=payload)
        try:
            resp = req.json()
        except requests.exceptions.JSONDecodeError:
            resp = {}
        if resp.get('status', '') == 'success':
            return resp.get('responsedata', {})
        else:
            syslog.syslog(
                syslog.LOG_ERR,
                f"{payload['action']} failed with status {resp['status']}. response: {resp}"
            )
            return {}
