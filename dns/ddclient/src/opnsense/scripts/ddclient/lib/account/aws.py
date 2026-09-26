"""
    Copyright (c) 2023 Greg Glockner <greg@glockners.net>
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
    AWS Route53 DNS provider
    Usage:
      AWS access key: username
      AWS secret key: password
      Route53 Hosted Zone ID: zone
"""
import syslog
import boto3
from . import BaseAccount


class AWS(BaseAccount):
    _services = ['aws']

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  AWS._services

    @staticmethod
    def match(account):
        return account.get('service') in AWS._services

    def execute(self):
        """ AWS DNS update
        """

        if super().execute():
            if not self.current_address:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    f"No address found for {self.description}"
                )
                return False
            client = boto3.client('route53',
                                  aws_access_key_id = self.settings.get('username'),
                                  aws_secret_access_key = self.settings.get('password'))
            ip = str(self.current_address)
            if ':' in ip:
                addrType = 'AAAA'
            else:
                addrType = 'A'
            TTL = self.settings.get('ttl')
            changeBatch = {
                'Changes': [{
                    'Action': 'UPSERT',
                    'ResourceRecordSet': {
                        'Name': host,
                        'Type': addrType,
                        'TTL': int(TTL),
                        'ResourceRecords': [{'Value': ip}]
                    }
                } for host in self.settings.get('hostnames').split(",")]
            }
            try:
                response = client.change_resource_record_sets(
                    HostedZoneId = self.settings.get('zone'),
                    ChangeBatch = changeBatch)
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, str(e))
                return False

            syslog.syslog(
                syslog.LOG_NOTICE,
                f"Account {self.description} set new ip {self.current_address}, ID {response['ChangeInfo']['Id']}")

            self.update_state(address=self.current_address)
            return True
