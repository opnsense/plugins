import syslog
import requests
from . import BaseAccount


class DeSEC(BaseAccount):
    _checked_values = {'1', 'true', 'yes', 'on'}
    _preserve_value = 'preserve'
    _user_agent = 'OPNsense-dyndns'

    _services = {
        'desec-v4': {
            'label': 'deSEC (IPv4)',
            'server': 'update.dedyn.io',
            'address_param': 'myipv4',
            'other_param': 'myipv6',
            'prune_setting': 'prune_aaaa'
        },
        'desec-v6': {
            'label': 'deSEC (IPv6)',
            'server': 'update6.dedyn.io',
            'address_param': 'myipv6',
            'other_param': 'myipv4',
            'prune_setting': 'prune_a'
        }
    }

    @classmethod
    def known_services(cls):
        return {key: item['label'] for key, item in cls._services.items()}

    @classmethod
    def match(cls, account):
        return account.get('service') in cls._services

    @classmethod
    def _is_checked(cls, value):
        return value is True or str(value).lower() in cls._checked_values

    def _token_secret(self):
        # Legacy deSEC accounts stored the token secret in "password"; a deSEC
        # account password was never accepted by this backend.
        return self.settings.get('token_secret') or self.settings.get('password') or ''

    def _address_parameters(self, service_settings):
        # deSEC prunes the other address family when its parameter is empty.
        # New accounts preserve by default; migrated accounts may set prune_* to
        # keep the historic behavior.
        other_address = (
            ''
            if self._is_checked(self.settings.get(service_settings['prune_setting'], False))
            else self._preserve_value
        )
        return {
            'hostname': self.settings.get('hostnames'),
            service_settings['address_param']: str(self.current_address),
            service_settings['other_param']: other_address
        }

    def _request_options(self, service_settings):
        uri_proto = 'https' if self.settings.get('force_ssl', False) else 'http'
        # deSEC's dynDNS "username" is the domain being updated, not an
        # account login. We already send that via the hostname parameter, so use
        # token authentication and keep the generic Username field irrelevant.
        return {
            'url': f"{uri_proto}://{service_settings['server']}/nic/update",
            'params': self._address_parameters(service_settings),
            'headers': {
                'User-Agent': self._user_agent,
                'Authorization': f"Token {self._token_secret()}"
            }
        }

    def execute(self):
        if not super().execute():
            return False

        service_settings = self._services[self.settings.get('service')]
        req = requests.get(**self._request_options(service_settings))

        if 200 <= req.status_code < 300:
            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s set new ip %s [%s]" % (self.description, self.current_address, req.text.strip())
                )

            self.update_state(address=self.current_address, status=req.text.split()[0] if req.text else '')
            return True

        syslog.syslog(
            syslog.LOG_ERR,
            "Account %s failed to set new ip %s [%d - %s]" % (
                self.description, self.current_address, req.status_code, req.text.replace('\n', '')
            )
        )
        return False
