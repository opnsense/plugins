#!/usr/bin/env python3

import logging
import json
import subprocess
import urllib.parse
from typing import cast, Any
import yaml

logging.basicConfig(level=logging.INFO)


def is_ipv6(ip: str) -> bool:
    return ":" in ip

def load_config(filename: str) -> dict[str, Any]:
    with open(filename) as fin:
        return yaml.safe_load(fin)


# only save if some value has changed
def save_config(filename: str, new_config: dict[str, Any]):
    old_config = load_config(filename)
    if old_config != new_config:
        with open(filename, 'w') as fout:
            yaml.dump(new_config, fout)


def get_netloc(settings: dict[str, str]):
    # defaults if config has not been saved yet
    listen_address = settings.get('lapi_listen_address', '127.0.0.1')
    listen_port = settings.get('lapi_listen_port', '8080')
    if is_ipv6(listen_address):
        listen_address = '[{}]'.format(listen_address)
    return '{}:{}'.format(listen_address, listen_port)


def get_new_url(old_url: str, settings: dict[str, str]):
    old_tuple = urllib.parse.urlsplit(old_url)
    new_tuple = old_tuple._replace(netloc=get_netloc(settings))
    new_url = urllib.parse.urlunsplit(new_tuple)
    # client lapi requires a trailing slash for the path part
    # and no, query and fragment don't make much sense
    if not new_tuple.query and not new_tuple.fragment and not new_url.endswith('/'):
        new_url += '/'
    return new_url


def configure_agent(settings: dict[str, str]):
    config_path = '/usr/local/etc/crowdsec/config.yaml'
    config = load_config(config_path)

    config['common']['log_dir'] = '/var/log/crowdsec'
    config['crowdsec_service']['acquisition_dir'] = '/usr/local/etc/crowdsec/acquis.d/'
    config['db_config']['use_wal'] = True

    if not int(settings.get('lapi_manual_configuration', '0')):
        config['api']['server']['listen_uri'] = get_netloc(settings)

    save_config(config_path, config)


def configure_lapi(settings: dict[str, str]):
    config_path = '/usr/local/etc/crowdsec/config.yaml'
    config = load_config(config_path)

    enable = int(settings.get('lapi_enabled', '0'))
    config['api']['server']['enable'] = bool(enable)

    save_config(config_path, config)


def configure_lapi_credentials(settings: dict[str, str]):
    config_path = '/usr/local/etc/crowdsec/local_api_credentials.yaml'
    config = load_config(config_path)

    if not int(settings.get('lapi_manual_configuration', '0')):
        config['url'] = get_new_url(config['url'], settings)

    save_config(config_path, config)


def configure_bouncer(settings: dict[str, str]):
    config_path = '/usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml'
    config = load_config(config_path)

    config['log_dir'] = '/var/log/crowdsec'
    config['blacklists_ipv4'] = 'crowdsec_blocklists'
    config['blacklists_ipv6'] = 'crowdsec6_blocklists'
    config['retry_initial_connect'] = True
    config['pf'] = {'anchor_name': ''}

    if not int(settings.get('lapi_manual_configuration', '0')):
        config['api_url'] = get_new_url(config['api_url'], settings)

    save_config(config_path, config)


def enroll(settings: dict[str, str]):
    enroll_key = settings.get('enroll_key')
    if enroll_key:
        try:
            p = subprocess.run(['cscli', 'capi', 'status'], check=True, text=True, stdout=subprocess.PIPE)
            if "instance is enrolled" in p.stdout:
                logging.info("crowdsec instance is already enrolled")
                return
        except subprocess.CalledProcessError:
            return
        except Exception as e:
            logging.error("could not run command 'cscli' to perform enrollment: %s", e)

        try:
            logging.info("enrolling crowdsec instance, please accept the enrollment on https://app.crowdsec.net")
            _ = subprocess.run(
                    ['cscli', 'console', 'enroll', '-e', 'context', enroll_key],
                    check=True, text=True)
        except subprocess.CalledProcessError as e:
            logging.error("enrollment failed: %s", e)
            return
        except Exception as e:
            logging.error("could not run command 'cscli' to perform enrollment: %s", e)


def main():
    try:
        with open('/usr/local/etc/crowdsec/opnsense/settings.json') as f:
            settings = cast(dict[str, str], json.load(f))
    except FileNotFoundError:
        logging.info("settings.json not found, won't change crowdsec config")
        return

    configure_agent(settings)
    configure_lapi(settings)
    configure_lapi_credentials(settings)
    enroll(settings)
    configure_bouncer(settings)


if __name__ == '__main__':
    main()
