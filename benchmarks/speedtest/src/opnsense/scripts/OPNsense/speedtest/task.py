#!/usr/local/bin/python3
# -*- coding: utf-8 -*-
"""
 * Copyright (C) 2021 M. Kralj
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
"""
import sys
import csv 
import json
import time
import subprocess
import os.path
from os import path

# A wrapper around Ookla speedtest binary: get arrguments (if any), run the test,
# store it in cvs, mirror the output that came from the test
arg=''
if len(sys.argv)>1:
    arg = str(sys.argv[1])
    arg = '-s'+str(sys.argv[1])
    if arg == '-s0':
        arg = ''

csvfile = "/usr/local/opnsense/scripts/OPNsense/speedtest/speedtest.csv"
fields = ['Timestamp', 'ClientIp', 'ServerId', 'ServerName', 'Latency', 'Jitter', 'DlSpeed', 'UlSpeed', 'Link'] 
# if CSV doesn't exist, we make one
p = not path.isfile(csvfile)
if p:
    f = open(csvfile, 'a')
    csv.writer(f).writerow(fields) 
    f.close()
# try-catch wrapper as we call the speedtest binary that migh fail
try:
    # --accept-license and --accept-gdpr allow speedtest binary to run the first time without user interaction
    # if there is no arg, speedtest will choose the server with the lowest latency
    tt=subprocess.run(['/usr/local/opnsense/scripts/OPNsense/speedtest/speedtest', '--accept-license', '--accept-gdpr', 
                       '-fjson', arg], stdout=subprocess.PIPE).stdout.decode('utf-8')
    # parsing the json output from speedtest
    testjson = json.loads(tt)
    Timestamp = testjson['timestamp']
    Timestamp = Timestamp[:10]+" "+Timestamp[11:19]
    ClientIp = testjson['interface']['externalIp']
    ServerId = testjson['server']['id']
    ServerName = testjson['server']['name']+", "+testjson['server']['location']
    Latency = testjson['ping']['latency']
    Jitter = testjson['ping']['jitter']
    DlSpeed = testjson['download']['bandwidth']/125000
    UlSpeed = testjson['upload']['bandwidth']/125000
    Testlink = testjson['result']['url']
    # creating a new data row and saving it to csv file
    newrow = [Timestamp, ClientIp, ServerId, ServerName, Latency, Jitter, DlSpeed, UlSpeed, Testlink] 
    f = open(csvfile, 'a')
    write = csv.writer(f) 
    write.writerow(newrow)
    # returning the exact same json output we got from speedtest
    print(tt, file=sys.stdout)

except subprocess.CalledProcessError as tt:
    print('speedtest binary '+str(tt.output)[2:], file=sys.stderr)
    sys.exit(0)