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
import urllib3
import datetime
import ujson
import telemetry.log
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

parser = argparse.ArgumentParser()
parser.add_argument('-l', '--log', help='log directory containing eve.json files', default="/var/log/suricata/")
parser.add_argument('-t', '--time', help='max seconds to read from now()', type=int, default=3600)
parser.add_argument('-p', '--parsed', help='show data as shipped using send_telemetry',
                    default=False, action="store_true")
parser.add_argument('-L', '--limit', help='limit number of rows', type=int, default=-1)
args = parser.parse_args()

last_update = datetime.datetime.now() - datetime.timedelta(seconds=float(args.time))

event_collector = telemetry.EventCollector()
row_count = 0
for record in telemetry.log.reader(args.log, last_update=last_update):
    if args.parsed:
        event_collector.push(record)
    else:
        print (ujson.dumps(record))

    row_count += 1
    if args.limit != -1 and row_count >= args.limit:
        break

if args.parsed:
    for push_data in event_collector:
        print (push_data.strip())
