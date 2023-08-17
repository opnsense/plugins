"""
    DuckDNS updater
    Token should be set via the password field
"""
import syslog
import requests
from . import BaseAccount


class duckdns(BaseAccount):
    _services = ['duckdns']

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return  duckdns._services

    @staticmethod
    def match(account):
        return account.get('service') in duckdns._services

    def execute(self):
        """ Duck DNS update
        """

        if super().execute():
            data = {
                'domains': self.settings.get('hostnames'),
                'token': self.settings.get('password')
            }

            ip = str(self.current_address)
            if ':' in ip:
                data['ipv6'] = ip
            else:
                data['ip'] = ip

            proto = 'https' if self.settings.get('force_ssl', False) else 'http'

            try:
                response = requests.get(proto+'://www.duckdns.org/update', data)
                if response.text.startswith('KO'):
                    raise RuntimeError(
                        f"DuckDNS update failed for {self.description} with ip {self.current_address} for domains {data['domains']}, response: {response.text}")
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, str(e))
                return False

            syslog.syslog(
                syslog.LOG_NOTICE,
                f"Account {self.description} set new ip {self.current_address} for domains {data['domains']}")

            self.update_state(address=self.current_address)
            return True
