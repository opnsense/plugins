#!/usr/local/bin/python3

"""
    Copyright (c) 2018-2019 Ad Schellevis <ad@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""

import argparse
import requests
import urllib3
import ujson
import telemetry
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

parser = argparse.ArgumentParser()
parser.add_argument('-e', '--endpoint', help='Endpoint url to reach',
                    default="%s/api/v1/sensorinfo" % telemetry.BASE_URL)
parser.add_argument('-i', '--insecure', help='Insecure, skip certificate validation',
                    action="store_true", default=False)
parser.add_argument('-c', '--config', help='rule downloader configuration',
                    default="/usr/local/etc/suricata/rule-updater.config")
args = parser.parse_args()

cnf = telemetry.get_config(args.config)
if cnf.token is not None:
    try:
        req = requests.get(args.endpoint, headers={'Authorization': 'Bearer %s' % cnf.token}, verify=not args.insecure)
        if req.status_code == 200:
            response = ujson.loads(req.text)
            response['status'] = 'ok'
        else:
            response = {'status': 'failed', 'response': req.text}
    except requests.exceptions.SSLError as e:
        response = {'status': 'failed', 'response': '%s' % e}
    except ValueError:
        response = {'status': 'failed', 'response': req.text}
else:
    response = {'status': 'unconfigured'}

print (ujson.dumps(response))
