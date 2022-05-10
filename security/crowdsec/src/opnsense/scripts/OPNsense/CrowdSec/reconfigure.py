#!/usr/bin/env python3

import logging
import json
import urllib.parse
import yaml

logging.basicConfig(level=logging.INFO)


def load_config(filename):
    with open(filename) as fin:
        return yaml.safe_load(fin)


# only save if some value has changed
def save_config(filename, new_config):
    old_config = load_config(filename)
    if old_config != new_config:
        with open(filename, 'w') as fout:
            yaml.dump(new_config, fout)


def get_netloc(settings):
    # defaults if config has not been saved yet
    listen_address = settings.get('lapi_listen_address', '127.0.0.1')
    listen_port = settings.get('lapi_listen_port', '8080')
    return '{}:{}'.format(listen_address, listen_port)


def get_new_url(old_url, settings):
    old_tuple = urllib.parse.urlsplit(old_url)
    new_tuple = old_tuple._replace(netloc=get_netloc(settings))
    new_url = urllib.parse.urlunsplit(new_tuple)
    # client lapi requires a trailing slash for the path part
    # and no, query and fragment don't make much sense
    if not new_tuple.query and not new_tuple.fragment and not new_url.endswith('/'):
        new_url += '/'
    return new_url


def configure_agent(settings):
    config_path = '/usr/local/etc/crowdsec/config.yaml'
    config = load_config(config_path)

    config['common']['log_dir'] = '/var/log/crowdsec'
    config['crowdsec_service']['acquisition_dir'] = '/usr/local/etc/crowdsec/acquis.d/'

    if not int(settings.get('lapi_manual_configuration', '0')):
        config['api']['server']['listen_uri'] = get_netloc(settings)

    save_config(config_path, config)


def configure_lapi_credentials(settings):
    config_path = '/usr/local/etc/crowdsec/local_api_credentials.yaml'
    config = load_config(config_path)

    if not int(settings.get('lapi_manual_configuration', '0')):
        config['url'] = get_new_url(config['url'], settings)

    save_config(config_path, config)


def configure_bouncer(settings):
    config_path = '/usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml'
    config = load_config(config_path)

    config['log_dir'] = '/var/log/crowdsec'
    config['blacklists_ipv4'] = 'crowdsec_blacklists'
    config['blacklists_ipv6'] = 'crowdsec6_blacklists'
    config['pf'] = {'anchor_name': ''}

    if not int(settings.get('lapi_manual_configuration', '0')):
        config['api_url'] = get_new_url(config['api_url'], settings)

    save_config(config_path, config)


def main():
    try:
        with open('/usr/local/etc/crowdsec/opnsense/settings.json') as f:
            settings = json.load(f)
    except FileNotFoundError:
        logging.info("settings.json not found, won't change crowdsec config")
        return

    configure_agent(settings)
    configure_lapi_credentials(settings)
    configure_bouncer(settings)


if __name__ == '__main__':
    main()
