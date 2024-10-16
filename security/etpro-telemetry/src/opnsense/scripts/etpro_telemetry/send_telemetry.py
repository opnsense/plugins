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
import os
import argparse
import requests
import time
import random
import syslog
import urllib3
import ujson
import telemetry.log
import telemetry.state

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

parser = argparse.ArgumentParser()
parser.add_argument('-e', '--endpoint', help='Endpoint url to reach',
                    default="%s/api/v1/event" % telemetry.BASE_URL)
parser.add_argument('-i', '--insecure', help='Insecure, skip certificate validation',
                    action="store_true", default=False)
parser.add_argument('-c', '--config', help='rule downloader configuration',
                    default="/usr/local/etc/suricata/rule-updater.config")
parser.add_argument('-l', '--log', help='log directory containing eve.json files',
                    default="/var/log/suricata/")
parser.add_argument('-s', '--state', help='persistent state (and lock) filename',
                    default="/usr/local/var/run/et_telemetry.state")
parser.add_argument('-d', '--days', help='Maximum number of days to look back', type=float, default=1)
parser.add_argument('-D', '--direct',
                    help='do not sleep before send (disable traffic spread)',
                    action="store_true",
                    default=False)
args = parser.parse_args()


exit_code = -1
send_start_time = time.time()
telemetry_state = telemetry.state.Telemetry(filename=args.state, init_last_days=args.days)
if not telemetry_state.is_running():
    cnf = telemetry.get_config(args.config)
    if cnf.token is not None:
        if os.path.isdir(args.log):
            last_update = telemetry_state.get_last_update()
            event_collector = telemetry.EventCollector()
            row_count = 0
            max_timestamp = None
            for record in telemetry.log.reader(args.log, last_update):
                if max_timestamp is None or record['__timestamp__'] > max_timestamp:
                    max_timestamp = record['__timestamp__']
                event_collector.push(record)
                row_count += 1
            # data collected, log and push
            if row_count > 0 and max_timestamp is not None:
                syslog.syslog(
                    syslog.LOG_DEBUG,
                    'telemetry data collected %d records in %.2f seconds @%s' % (
                        row_count, time.time() - send_start_time, max_timestamp
                    )
                )
                # spread traffic to remote host, usual cron interval is 1 minute
                if not args.direct:
                    time.sleep(random.randint(0, 60))
                # the eventcollector loop sets exit_code when issues ocure, no data processed doesn't mean
                # anything is wrong (it's just not of interest to Proofpoint).
                exit_code = 0
                for push_data in event_collector:
                    params = {
                        'timeout': 5,
                        'headers': {'Authorization': 'Bearer %s' % cnf.token},
                        'data': push_data.strip()
                    }
                    if args.insecure:
                        params['verify'] = False

                    r = requests.post(args.endpoint, **params)
                    if r.status_code != 201:
                        syslog.syslog(
                            syslog.LOG_ERR,
                            'unexpected result from %s (http_code %s)' % (args.endpoint, r.status_code)
                        )
                        exit_code = -1
                        break
                    else:
                        try:
                            ujson.loads(r.text)
                        except ValueError:
                            syslog.syslog(syslog.LOG_ERR, 'telemetry unexpected response %s' % r.text[:256])
                            exit_code = -1
                            break
                if exit_code == 0:
                    # update timestamp, last record processed
                    telemetry_state.set_last_update(max_timestamp)
            else:
                # no data
                exit_code = 0
        else:
            syslog.syslog(syslog.LOG_ERR, 'telemetry token missing in %s' % args.config)
    else:
        syslog.syslog(syslog.LOG_ERR, 'directory %s missing' % args.log)


sys.exit(exit_code)
