"""
    Copyright (c) 2023 Ingo Lafrenz <opnsense@der-ingo.de>
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
    _netcupAPIURL = 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON'
    _netcupMinTTL = 300
    _escapeSequence = '###NC-SEPARATOR###'

    def __init__(self, account: dict):
        super().__init__(account)
        self.netcupCustomerNr = self.settings.get('username')
        self.netcupAPIPassword, self.netcupAPIKey = [Netcup._unescape(x) for x in Netcup._escape(self.settings.get('password')).split(':', 1)]
        hostnames = self.settings.get('hostnames').split(',')
        if len(hostnames) > 1:
            syslog.syslog(syslog.LOG_WARNING, "Multiple hostnames detected, ignoring all except first. Consider using CNAMEs or create separate DynDNS instances for each hostname.")
        self.hostname, self.domain = hostnames[0].split('.', 1)
        self.ttl = int(self.settings.get('ttl', '300'))
        if self.ttl < Netcup._netcupMinTTL:
            syslog.syslog(syslog.LOG_WARNING, f'TTL was auto corrected to {Netcup._netcupMinTTL}s since Netcup doesn''t allow smaller values.')
            self.ttl = Netcup._netcupMinTTL

    @staticmethod
    def known_services():
        return Netcup._services

    @staticmethod
    def match(account):
        return account.get('service') in Netcup._services

    @staticmethod
    def _escape(x):
        return x.replace('\\:', Netcup._escapeSequence)

    @staticmethod
    def _unescape(x):
        return x.replace(Netcup._escapeSequence, ':')

    def execute(self):
        super().execute()
        netcupAPISessionID = self._login()
        dnsZoneInfo = self._infoDnsZone(netcupAPISessionID)
        ttl = dnsZoneInfo['ttl']
        if str(self.ttl) != ttl:
            dnsZoneInfo['ttl'] = str(self.ttl)
            self._updateDNSZone(dnsZoneInfo, netcupAPISessionID)
        dnsRecordsInfo = self._infoDnsRecords(netcupAPISessionID)
        recordType = 'AAAA' if ':' in self.current_address else 'A'
        self._updateIpAddress(recordType, dnsRecordsInfo, netcupAPISessionID)
        self._logout(netcupAPISessionID)
        self.update_state(address=self.current_address)
        return True

    def _login(self):
        requestPayload = {
            'action': 'login',
            'param': {
                'customernumber': self.netcupCustomerNr,
                'apikey': self.netcupAPIKey,
                'apipassword': self.netcupAPIPassword
            }
        }
        return Netcup._sendRequest(requestPayload)['responsedata']['apisessionid']

    def _infoDnsZone(self, netcupAPISessionID):
        return Netcup._sendRequest(self._createRequestPayload('infoDnsZone', netcupAPISessionID))['responsedata']

    def _updateDNSZone(self, dnsZone, netcupAPISessionID):
        return Netcup._sendRequest(self._createRequestPayload('updateDnsZone', netcupAPISessionID, {'dnszone': dnsZone}))['responsedata']

    def _infoDnsRecords(self, netcupAPISessionID):
        return Netcup._sendRequest(self._createRequestPayload('infoDnsRecords', netcupAPISessionID))['responsedata']

    def _updateDnsRecords(self, hostRecord, netcupAPISessionID):
        return Netcup._sendRequest(self._createRequestPayload('updateDnsRecords', netcupAPISessionID, {'dnsrecordset': {'dnsrecords': [hostRecord]}}))['responsedata']

    def _logout(self, netcupAPISessionID):
        requestPayload = {
            'action': 'logout',
            'param': {
                'customernumber': self.netcupCustomerNr,
                'apikey': self.netcupAPIKey,
                'apisessionid': netcupAPISessionID
            }
        }
        return Netcup._sendRequest(requestPayload)['responsedata']

    def _updateIpAddress(self, recordType, dnsRecordsInfo, netcupAPISessionID):
        matchingRecords = [r for r in dnsRecordsInfo['dnsrecords'] if r['type'] == recordType and r['hostname'] == self.hostname]
        if len(matchingRecords) > 1:
            raise Exception(f'Too many {recordType} records for hostname {self.hostname} in DNS zone {self.domain}.')
        hostRecord = {
            'id': matchingRecords[0]['id'],
            'hostname': matchingRecords[0]['hostname'],
            'type': matchingRecords[0]['type'],
            'priority': matchingRecords[0]['priority'],
            'destination': matchingRecords[0]['destination'],
            'deleterecord': matchingRecords[0]['deleterecord'],
            'state': matchingRecords[0]['state'],
        } if matchingRecords else {
            'hostname': self.hostname,
            'type': recordType,
            'destination': None
        }
        currentNetcupIPAddress = hostRecord['destination']
        if self.current_address != currentNetcupIPAddress:
            syslog.syslog(syslog.LOG_NOTICE, f'IP address change detected. Old IP: {currentNetcupIPAddress}, new IP: {self.current_address}')
            hostRecord['destination'] = self.current_address
            self._updateDnsRecords(hostRecord, netcupAPISessionID)
            syslog.syslog(syslog.LOG_NOTICE, f'Successfully updated {recordType} record for {self.hostname}.{self.domain} to {self.current_address}')
        else:
            syslog.syslog(syslog.LOG_NOTICE, 'IP address has not changed. Nothing to do.')

    def _createRequestPayload(self, action, netcupAPISessionID, extraParameters={}):
        requestPayload = {
            'action': action,
            'param': {
                'domainname': self.domain,
                'customernumber': self.netcupCustomerNr,
                'apikey': self.netcupAPIKey,
                'apisessionid': netcupAPISessionID,
            }
        }
        requestPayload['param'].update(extraParameters)
        return requestPayload

    @staticmethod
    def _sendRequest(requestPayload):
        response = requests.post(Netcup._netcupAPIURL, json=requestPayload).json()
        if response['status'] == 'success':
            return response
        raise Exception(f"{requestPayload['action']} failed with status {response['status']}. response: {response}")
