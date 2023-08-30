"""
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
    Azure DNS provider, inspired by https://github.com/opnsense/plugins/pull/1547

    List DNS zones using Azure cloud shell

    #> az network dns zone list

    Returns a structure like:

    [
      {
        "etag": "00000000-0000-0000-0000-0000000000000",
        "id": "/subscriptions/00000000-0000-0000-0000-00000000000/resourceGroups/example/providers/Microsoft.Network/dnszones/example.com",   <---- ResourceId
        "location": "global",
        "maxNumberOfRecordSets": 10000,
        "maxNumberOfRecordsPerRecordSet": null,
        "name": "test.deciso.com",
        "nameServers": [
          "ns1-07.azure-dns.com.",
          "ns2-07.azure-dns.net.",
          "ns3-07.azure-dns.org.",
          "ns4-07.azure-dns.info."
        ],
        "numberOfRecordSets": 3,
        "registrationVirtualNetworks": null,
        "resolutionVirtualNetworks": null,
        "resourceGroup": "xxxxxx",
        "tags": {},
        "type": "Microsoft.Network/dnszones",
        "zoneType": "Public"
      }
    ]

    Next create a service principal (https://learn.microsoft.com/en-us/cli/azure/ad/sp?view=azure-cli-latest#az_ad_sp_create_for_rbac)

    #> az ad sp create-for-rbac --name  "AcmeDnsValidator" --role "DNS Zone Contributor" --scopes /subscriptions/00000000-0000-0000-0000-00000000000/resourceGroups/example/providers/Microsoft.Network/dnszones/example.com

    Which returns a structure like:

    {
      "appId": "00000000-0000-0000-0000-000000000000",      <--- username
      "displayName": "AcmeDnsValidator",
      "password": "000000000000000000000000000000000000",   <--- Password
      "tenant": "00000000-0000-0000-0000-000000000000"
    }
"""
import syslog
import requests
from requests.auth import HTTPBasicAuth
from . import BaseAccount


class Azure(BaseAccount):
    _services = ['azure']

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  Azure._services

    @staticmethod
    def match(account):
        return account.get('service') in Azure._services

    def execute(self):
        """ Azure DNS update, uses an oauth2 sequence to login, the following requests are being performed:
            - https://management.azure.com/subscriptions/%s --> request target to authenticate against
            - https://login.microsoftonline.com/%s/oauth2/token --> login using the tenantId received in the prev req
            - https://management.azure.com/%s/XXX/%s --> set hostname address using the bearer token received
        """
        if super().execute():
            resourceId = self.settings.get('resourceId', '')
            if resourceId.find('subscriptions/') == -1:
                syslog.syslog(syslog.LOG_ERR, 'No subscription id found for account %s' % self.description)
                return
            subscriptionId = resourceId.split('subscriptions/')[-1].split('/')[0]
            req = requests.get('https://management.azure.com/subscriptions/%s?api-version=2016-09-01' % subscriptionId)
            auth_target = req.headers.get('WWW-Authenticate', '').split(maxsplit=1)
            if len(auth_target) < 2 or auth_target[0] != 'Bearer':
                syslog.syslog(syslog.LOG_ERR, 'No Bearer token found for account %s' % self.description)
                return
            elif auth_target[1].find('https://login.windows.net/') == -1:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'Unable to find Tenant ID for account %s (response: %s)' % (self.description, auth_target[1])
                )
                return

            tenantId = auth_target[1].split('https://login.windows.net/')[1].split('"')[0]
            req_opts = {
                'url':  'https://login.microsoftonline.com/%s/oauth2/token' % tenantId,
                'data': {
                    'resource': 'https://management.core.windows.net/',
                    'grant_type': 'client_credentials',
                    'client_id': self.settings.get('username'),
                    'client_secret': self.settings.get('password')
                },
                'headers': {
                    'User-Agent': 'OPNsense-dyndns'
                }
            }
            req = requests.post(**req_opts)
            try:
                token_payload = req.json()
            except requests.exceptions.JSONDecodeError:
                token_payload = {}
            if req.status_code != 200  or 'access_token' not in token_payload:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'Unable to authenticate account %s (http_code: %d - %s)' % (
                        self.description,
                        req.status_code,
                        req.text.replace('\n', '')
                    )
                )
                return

            for hostname in self.settings.get('hostnames', '').split(','):
                req_opts = {
                    'headers': {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer %s' % token_payload['access_token'],
                        'Content-Type': 'application/json'
                    }
                }
                if self.current_address.find(':') > 1:
                    # IPv6
                    req_opts['url'] = 'https://management.azure.com/%s/AAAA/%s?api-version=2018-05-01' % (
                        resourceId, hostname
                    )
                    req_opts['json'] = {
                        'properties': {
                            'AAAARecords': [
                                {
                                    'ipv6Address': self.current_address
                                }
                            ]
                        }
                    }
                else:
                    #IPv4
                    req_opts['url'] = 'https://management.azure.com/%s/A/%s?api-version=2018-05-01' % (
                        resourceId, hostname
                    )
                    req_opts['json'] = {
                        'properties': {
                            'ARecords': [
                                {
                                    'ipv4Address': self.current_address
                                }
                            ]
                        }
                    }

                req = requests.patch(**req_opts)
                if req.status_code == 200:
                    if self.is_verbose:
                        syslog.syslog(
                            syslog.LOG_NOTICE,
                            "Account %s set new ip %s [%s]" % (self.description, self.current_address, req.text.strip())
                        )

                    self.update_state(address=self.current_address)
                    return True

        return False
