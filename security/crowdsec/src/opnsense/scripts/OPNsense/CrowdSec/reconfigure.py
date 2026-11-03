#!/usr/bin/env python3

import logging
import json
import os
import subprocess
import urllib.parse
from typing import cast, Any
import yaml

logging.basicConfig(level=logging.INFO)

# connection settings (url, api key) of the bouncer for the local LAPI, kept
# while it connects to a remote LAPI. The directory is only readable by root.
LOCAL_BOUNCER_SETTINGS_PATH = '/usr/local/etc/crowdsec/opnsense/local_bouncer.json'


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


def with_trailing_slash(url: str) -> str:
    # client lapi requires a trailing slash for the path part
    # and no, query and fragment don't make much sense
    url_tuple = urllib.parse.urlsplit(url)
    if not url_tuple.query and not url_tuple.fragment and not url.endswith('/'):
        url += '/'
    return url


def get_new_url(old_url: str, settings: dict[str, str]):
    old_tuple = urllib.parse.urlsplit(old_url)
    new_tuple = old_tuple._replace(netloc=get_netloc(settings))
    return with_trailing_slash(urllib.parse.urlunsplit(new_tuple))


def bouncer_uses_remote_lapi(settings: dict[str, str]) -> bool:
    return settings.get('bouncer_lapi', 'local') == 'remote'


def configure_agent(settings: dict[str, str]):
    config_path = '/usr/local/etc/crowdsec/config.yaml'
    config = load_config(config_path)

    config['common']['log_dir'] = '/var/log/crowdsec'
    config['crowdsec_service']['acquisition_dir'] = '/usr/local/etc/crowdsec/acquis.d/'
    config['db_config']['use_wal'] = True

    enable = int(settings.get('agent_enabled', '0'))
    config['crowdsec_service']['enable'] = bool(enable)

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


def write_secret(filename: str, content: dict[str, Any]):
    fd = os.open(filename, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'w') as fout:
        json.dump(content, fout)


def local_bouncer_settings(config: dict[str, Any], settings: dict[str, str]) -> dict[str, Any]:
    # the current connection is kept only if it points to the local LAPI.
    # Otherwise (e.g. switching from a manual configuration to a remote
    # LAPI) the key is for another LAPI: restore the placeholder instead,
    # so the rc script registers the bouncer on the local LAPI again.
    if config.get('api_url') == get_new_url(config.get('api_url', ''), settings):
        return {'api_url': config['api_url'], 'api_key': config.get('api_key', '')}
    return {'api_url': with_trailing_slash('http://' + get_netloc(settings)), 'api_key': '${API_KEY}'}


def configure_bouncer(settings: dict[str, str]):
    config_path = '/usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml'
    config = load_config(config_path)

    config['log_dir'] = '/var/log/crowdsec'
    config['blacklists_ipv4'] = 'crowdsec_blocklists'
    config['blacklists_ipv6'] = 'crowdsec6_blocklists'
    config['retry_initial_connect'] = True
    config['pf'] = {'anchor_name': ''}

    if not int(settings.get('lapi_manual_configuration', '0')):
        if bouncer_uses_remote_lapi(settings):
            # keep the bouncer registration on the local LAPI, to restore it
            # when switching back
            if not os.path.exists(LOCAL_BOUNCER_SETTINGS_PATH):
                write_secret(LOCAL_BOUNCER_SETTINGS_PATH, local_bouncer_settings(config, settings))
            config['api_url'] = with_trailing_slash(settings.get('remote_lapi_url', ''))
            config['api_key'] = settings.get('remote_bouncer_api_key', '')
        else:
            if os.path.exists(LOCAL_BOUNCER_SETTINGS_PATH):
                with open(LOCAL_BOUNCER_SETTINGS_PATH) as fin:
                    config.update(json.load(fin))
                os.remove(LOCAL_BOUNCER_SETTINGS_PATH)
            config['api_url'] = get_new_url(config['api_url'], settings)

    save_config(config_path, config)
    # the file contains the api key
    os.chmod(config_path, 0o600)


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
