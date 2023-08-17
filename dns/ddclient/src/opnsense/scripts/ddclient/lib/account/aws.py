"""
    AWS Route53 DNS provider
    Usage:
      AWS access key: username
      AWS secret key: password
      Route53 Hosted Zone ID: resource ID (use advanced settings)
"""
import syslog
import boto3
from . import BaseAccount


## TODO:
## - TTL as parameter
## - Require the resource value

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
            TTL = "300" # placeholder
            client = boto3.client('route53',
                                  aws_access_key_id = self.settings.get('username'),
                                  aws_secret_access_key = self.settings.get('password'))
            ip = str(self.current_address)
            if ':' in ip:
                addrType = 'AAAA'
            else:
                addrType = 'A'
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
                    HostedZoneId = self.settings.get('resourceId', ''),
                    ChangeBatch = changeBatch)
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, str(e))
                return False

            syslog.syslog(
                syslog.LOG_NOTICE,
                f"Account {self.description} set new ip {self.current_address}, ID {response['ChangeInfo']['Id']}")

            self.update_state(address=self.current_address)
            return True
