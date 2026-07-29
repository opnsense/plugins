#!/usr/bin/env python3
# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

import json
import os
import sys
import time

PATH = os.environ.get('DNSBL_STATUS_PATH', '/var/run/bind/dnsbl-status.json')

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
directory = os.path.dirname(PATH)
if directory:
    os.makedirs(directory, exist_ok=True)
tmp = PATH + '.tmp'
with open(tmp, 'w') as handle:
    json.dump(status, handle)
os.replace(tmp, PATH)
