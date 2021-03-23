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

import csv 
import json
import time
from datetime import datetime
import statistics
import os.path
from os import path

fields = ['Timestamp', 'ClientIp', 'ServerId', 'ServerName', 'Latency', 'Jitter', 'DlSpeed', 'UlSpeed', 'Link'] 
csvfile = "/usr/local/opnsense/scripts/OPNsense/speedtest/speedtest.csv"
latencyarray = []
downloadarray = []
uploadarray = []
timearray = []
# if CSV doesn't exist, we make one
p = not path.isfile(csvfile)
if p:
    f = open(csvfile, 'a')
    csv.writer(f).writerow(fields) 
    f.close()

try:
    f = open(csvfile, 'r')
    data = csv.reader(f)
    line = 0
    for row in data:
        if line > 0:
            timearray.append(row[0])
            latencyarray.append(float(row[4]))
            downloadarray.append(float(row[6]))
            uploadarray.append(float(row[7]))
        line += 1
    line -= 1
    if line==0:
        latencyarray = [0]
        downloadarray = [0]
        uploadarray = [0]
        timearray = [0]
    
    avglat = statistics.mean(latencyarray)
    avgdl = statistics.mean(downloadarray)
    avgul = statistics.mean(uploadarray)
    timedelta = (datetime.strptime(max(timearray), '%Y-%m-%d %H:%M:%S') 
               - datetime.strptime(min(timearray), '%Y-%m-%d %H:%M:%S')).days
    out = {
        'samples': line,
        'period': {
            'oldest': min(timearray),
            'youngest': max(timearray),
            'days': timedelta
        },
        'latency': {
            'avg': round(statistics.mean(latencyarray),2),
            'min': round(min(latencyarray),2),
            'max': round(max(latencyarray),2),
            'last': round(float(row[4]),2)
            },
        'upload':  {
            'avg': round(statistics.mean(uploadarray),2),
            'min': round(min(uploadarray),2),
            'max': round(max(uploadarray),2),
            'last': round(float(row[7]),2)
            },
        'download': {
            'avg': round(statistics.mean(downloadarray),2),
            'min': round(min(downloadarray),2),
            'max': round(max(downloadarray),2),
            'last': round(float(row[6]),2)
        }
    }
    print(json.dumps(out))
finally:
    f.close()
