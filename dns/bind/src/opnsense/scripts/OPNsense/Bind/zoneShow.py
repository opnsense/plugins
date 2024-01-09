#!/usr/bin/env python3

# send primary zone file content to stdout

import sys
import os.path
import glob
import ujson

zone_name = sys.argv[1]
result = dict()
zone_config = []
zone_files_root = '/usr/local/etc/namedb/primary/'
zone_file = zone_files_root + zone_name + '.db'

def load_db_file(zone_file):
    """ load db-file
    """
    for line in open(zone_file, 'r').read().split('\n'):
        zone_config.append(line.rstrip())

if os.path.isfile(zone_file):
    result['path'] = zone_file
    result['time'] = os.path.getmtime(zone_file)
    load_db_file(zone_file)
    result['zone_content'] = zone_config
print(ujson.dumps(result))
