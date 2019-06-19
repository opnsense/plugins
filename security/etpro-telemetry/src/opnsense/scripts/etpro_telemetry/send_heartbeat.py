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

import sys
import argparse
import requests
import syslog
import time
import random
import urllib3
import telemetry
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

parser = argparse.ArgumentParser()
parser.add_argument('-e', '--endpoint',
                    help='Endpoint url to reach',
                    default="%s/api/v1/sensor" % telemetry.BASE_URL)
parser.add_argument('-i', '--insecure',
                    help='Insecure, skip certificate validation',
                    action="store_true",
                    default=False)
parser.add_argument('-c', '--config',
                    help='rule downloader configuration',
                    default="/usr/local/etc/suricata/rule-updater.config"
                    )
parser.add_argument('-D', '--direct',
                    help='do not sleep before send (disable traffic spread)',
                    action="store_true",
                    default=False)
args = parser.parse_args()

exit_code = -1
cnf = telemetry.get_config(args.config)
if cnf.token is not None:
    params = {'timeout': 5, 'headers': {'Authorization': 'Bearer %s' % cnf.token}}
    if args.insecure:
        params['verify'] = False
    try:
        # spread traffic to remote host, usual cron interval is 30 minutes
        if not args.direct:
            time.sleep(random.randint(0, 1800))
        r = requests.head(args.endpoint, **params)
        if r.status_code == 200:
            # expected result, set exit code
            exit_code = 0
        else:
            syslog.syslog(syslog.LOG_ERR, 'unexpected result from %s (http_code %s)' % (args.endpoint, r.status_code))
    except requests.exceptions.ConnectionError:
        syslog.syslog(syslog.LOG_ERR, 'connection error sending heardbeat to %s' % args.endpoint)
else:
    syslog.syslog(syslog.LOG_ERR, 'telemetry token missing in %s' % args.config)


# exit
sys.exit(exit_code)
