#!/usr/local/bin/python3
"""Render configuration without touching the firewall or using real secrets."""
import json
from pathlib import Path
from jinja2 import Environment

root = Path(__file__).resolve().parents[1] / 'src/opnsense/service/templates/OPNsense/Newt'
class Helpers:
    @staticmethod
    def exists(path):
        return True

cfg = dict(enabled='1', endpoint='https://pangolin.example.com', siteId='example',
           secret='quote" backslash\\ <>&', logLevel='INFO', mtu='1280', port='', dns='')
env = Environment()
context = {'OPNsense': {'Newt': {'general': cfg}}, 'helpers': Helpers()}
def render(name):
    return env.from_string((root / name).read_text()).render(**context)
data = json.loads(render('config.json'))
assert data['secret'] == cfg['secret']
assert data['mtu'] == 1280 and 'port' not in data and 'dns' not in data
assert 'newt_enable="YES"' in render('rc.conf.d')
cfg.update(enabled='0', port='51830', dns='9.9.9.9')
data = json.loads(render('config.json'))
assert data['port'] == 51830 and data['dns'] == '9.9.9.9'
assert 'newt_enable="NO"' in render('rc.conf.d')
print('Configuration template checks passed.')
