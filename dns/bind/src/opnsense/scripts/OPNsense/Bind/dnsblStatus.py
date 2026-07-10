#!/usr/bin/env python3
import json
import os
import sys
import time

PATH = '/var/run/bind/dnsbl-status.json'

status = {}
try:
    with open(PATH) as handle:
        status = json.load(handle)
except (OSError, ValueError):
    pass
if len(sys.argv) == 2 and sys.argv[1] == '--stage':
    print(status.get('stage', 'idle'))
    sys.exit(0)
status['stage'] = sys.argv[1]
status['message'] = ' '.join(sys.argv[2:])
status['updated_at'] = time.time()
if status['stage'] == 'starting':
    status['guard_started_at'] = int(time.time())
tmp = PATH + '.tmp'
with open(tmp, 'w') as handle:
    json.dump(status, handle)
os.replace(tmp, PATH)
