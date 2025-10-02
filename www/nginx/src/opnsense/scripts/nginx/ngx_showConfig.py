#!/usr/bin/env python3

# nginx -T shows the config only if the test succeeds
# grab nginx config from file(s) and send to stdout

import os.path
import glob
import ujson

result = dict()
nginx_config = []
nginx_config_root = '/usr/local/etc/nginx/'
nginx_config_file = nginx_config_root + 'nginx.conf'

def load_config_file(config_path):
    """ load config with all inclusions
    """
    config_incs = []
    # mimic 'nginx -T' syntax for config files references
    nginx_config.append('# configuration file ' + config_path + ':')
    for line in open(config_path, 'r').read().split('\n'):
        nginx_config.append(line.rstrip())
        line = line.strip()
        if line.startswith('include '):
            # only '*' mask is supported/used in plugin
            if '*' not in line:
                # it's a file relative path
                incfilepath = nginx_config_root + line.split(' ')[-1][:-1]
                if os.path.isfile(incfilepath):
                    config_incs.append(incfilepath)
            else:
                # it's a path with a file mask
                incdir = nginx_config_root + line.split(' ')[-1][:-1]
                for incfilepath in glob.glob(incdir):
                    config_incs.append(incfilepath)
    for inc in list(dict.fromkeys(config_incs)):
        load_config_file(inc)

if os.path.isfile(nginx_config_file):
    result['time'] = os.path.getmtime(nginx_config_file)
    load_config_file(nginx_config_file)
    result['config'] = nginx_config
print(ujson.dumps(result))
