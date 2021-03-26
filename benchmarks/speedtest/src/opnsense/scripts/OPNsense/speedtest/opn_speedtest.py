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
import re
import datetime
from datetime import datetime
import subprocess
import statistics
import os.path
from os import path

# A wrapper around speedtest.py CLI: get arrguments (if any), run the test,
# store it in cvs, mirror the output that came from the test
def is_int (n):
    try:
        int(n)
        return True
    except ValueError:
        return False

#speedtest = "/usr/local/opnsense/scripts/OPNsense/speedtest/speedtest"
speedtest = "speedtest"
csvfile = "/usr/local/opnsense/scripts/OPNsense/speedtest/speedtest.csv"

arg=''
if len(sys.argv)>1:
    arg=str(sys.argv[1])
fields = ['Timestamp', 'ClientIp', 'ServerId', 'ServerName', 'Country', 'DlSpeed', 'UlSpeed', 'Latency', 'Link'] 

try:
    # if CSV doesn't exist, we make one
    p = not path.isfile(csvfile)
    if p:
        f = open(csvfile, 'a', encoding="utf-8")
        csv.writer(f,dialect='excel').writerow(fields) 
        f.close()

    # parameter l or log - returning the last 50 entries from csv
    if arg=='l' or arg == 'log':
        array=[]
        f = open(csvfile, 'r', encoding="utf-8")
        data = csv.reader(f,dialect='excel')
        header = next(data)
        for row in data:
            #from timestamp to visual form
            row[0]=datetime.fromtimestamp(float(row[0])).isoformat()
            array.append(row)
        array=sorted(array, reverse=True)
        f.close()
        print(json.dumps(array[:50]))
        quit()

    # parameter s or stat - return statistics
    if arg=='s' or arg=='stat':
        latencyarray = []
        downloadarray = []
        uploadarray = []
        timearray = []
        f = open(csvfile, 'r', encoding="utf-8")
        data = csv.reader(f,dialect='excel')
        line = 0
        for row in data:
            if line > 0:
                timearray.append(datetime.fromtimestamp(float(row[0])))
                downloadarray.append(float(row[5]))
                uploadarray.append(float(row[6]))
                latencyarray.append(float(row[7]))
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
        if line == 0:
            timedelta = 0
        else:
            timedelta = (max(timearray)- min(timearray)).days
        out = {
            'samples': line,
            'period': {
                'oldest': str(min(timearray)),
                'youngest': str(max(timearray)),
                'days': timedelta
            },
            'latency': {
                'avg': round(statistics.mean(latencyarray),2),
                'min': round(min(latencyarray),2),
                'max': round(max(latencyarray),2),
                },
            'download': {
                'avg': round(statistics.mean(downloadarray),2),
                'min': round(min(downloadarray),2),
                'max': round(max(downloadarray),2),
                },
            'upload':  {
                'avg': round(statistics.mean(uploadarray),2),
                'min': round(min(uploadarray),2),
                'max': round(max(uploadarray),2),
                }
        }
        print(json.dumps(out))
        quit()

    # checking the version of speedtest
    version = subprocess.run([speedtest, "--version"], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=True).stdout.decode('utf-8')
    bin_version = version.find("Ookla")>0

    # parameter v or version - returning the version string 
    if arg=='v' or arg == 'version':
        if bin_version:
            out={"version":"binary", "message":version.splitlines()[0]}
        else:
            out={"version":"cli", "message":version.splitlines()[0]+' '+version.splitlines()[1]}
        print(json.dumps(out))
        quit()

    # parameter q or list - returning the list of servers available
    if arg=='t' or arg=='list':
        array=[]
        if bin_version:
            # when binary, we cut out the lat and lon of the server for consistency
            cmd = [speedtest, '--accept-license', '--accept-gdpr', '--servers', '-fjsonl']
            serverlist = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=True).stdout.decode('utf-8').splitlines()
            for line in serverlist:
                tt = json.loads(line)
                out = {'id':str(tt['id']), 'name':tt['name'], 'location':tt['location'], 'country':tt['country']}
                array.append(out)
        else:
            # when http, we reassemble the json from the text output; speedtest-cli can't return json list of servers
            cmd = [speedtest, '--list']
            serverlist = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=True).stdout.decode('utf-8').splitlines()
            for line in serverlist[1:11]:
                rec = re.split("\) | \(|, ", line)
                out = {'id':rec[0].strip(), "name":rec[1].strip(), "location":rec[2].strip()+", "+rec[3], "country":rec[4].strip()}
                array.append(out)
        print(json.dumps(array))
        quit()
    # running code with no arguments or with '0' lets speedtest to decide what server to poll
    elif arg == '' or arg == '0':
        if bin_version:
            cmd = [speedtest, '--accept-license', '--accept-gdpr', '-fjson']
        else:
            cmd = [speedtest, '--json', '--share']
    # running code with integer: supply the argument as an ID of the seerver
    elif is_int(arg):
        if bin_version:
            cmd = [speedtest, '--accept-license', '--accept-gdpr', '-fjson', '-s'+arg]
        else:
            cmd = [speedtest, '--json', '--share', '--server', arg]
    # running the speedtest with the right parameters
    else:
        out={'error': str(arg)+" is invalid server id"}
        print(json.dumps(out))
        quit()
    result = json.loads(subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=True).stdout.decode('utf-8'))
    # assembling the output json to be consistent regarless of what came back from speedtest
    out = {}
    if bin_version:
        out['timestamp'] = result['timestamp']
        out['clientip'] = result['interface']['externalIp']
        out['serverid'] = result['server']['id']
        out['servername'] = result['server']['name']+", "+result['server']['location']
        out['country'] = result['server']['country']
        out['latency'] = round(result['ping']['latency'],2)
        out['download'] = round(result['download']['bandwidth']/125000,2)
        out['upload'] = round(result['upload']['bandwidth']/125000,2)
        out['link'] = result['result']['url']
    else:
        out['timestamp'] = result['timestamp'][:-8]+'Z'
        out['clientip'] = result['client']['ip']
        out['serverid'] = result['server']['id']
        out['servername'] = result['server']['sponsor']+', '+result['server']['name']
        out['country'] = result['server']['country']
        out['latency'] = round(result['ping'],2)
        out['download'] = round(result['download']/1000000,2)
        out['upload'] = round(result['upload']/1000000,2)
        out['link'] = result['share'][:-4]
    # datetime in CSV uses different format
    csvtime = datetime.strptime(out['timestamp'], "%Y-%m-%dT%H:%M:%SZ").timestamp()
    newrow = [csvtime, out['clientip'], out['serverid'], out['servername'], out['country'], out['download'], out['upload'], out['latency'], out['link']] 
    # writing the newrow into csv
    f = open(csvfile, 'a', encoding="utf-8")
    write = csv.writer(f,dialect='excel') 
    write.writerow(newrow)
    f.close()
    # returning the assembled json for further processing
    print(json.dumps(out), file=sys.stdout)
except OSError or IOError:
    out={'version':'none', 'message':'No speedtest package installed'}
    print(json.dumps(out))
except subprocess.CalledProcessError as e:
    out={'error':'Speedtest server id '+str(arg)+" not recognized."}
    print(json.dumps(out))