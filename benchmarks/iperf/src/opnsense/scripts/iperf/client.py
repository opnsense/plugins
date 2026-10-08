#!/usr/local/bin/python3

"""
Copyright (C) 2026 François Maymil
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
    --------------------------------------------------------------------------------------
    run iperf3 in client mode as a background job, settings and results are kept in /tmp
"""
import argparse
import glob
import os
import subprocess
import ujson
from datetime import datetime

JOB_DIR = '/tmp/iperf/'
# iperf3 can wait forever on a server that accepts the connection but never starts the test
GRACE_TIME = 30


def job_pids(jobid):
    pids = []
    args = ['/bin/pgrep', '-f', "%s%s" % (JOB_DIR, jobid)]
    for line in subprocess.run(args, capture_output=True, text=True).stdout.split():
        if line.isdigit():
            pids.append(line)
    return pids


def load_json(filename):
    try:
        with open(filename, 'r') as f:
            return ujson.load(f)
    except (OSError, ValueError):
        return None


def interface_address(intf):
    output = subprocess.run(['/sbin/ifconfig', intf, 'inet'], capture_output=True, text=True).stdout
    for line in output.split("\n"):
        parts = line.split()
        if len(parts) > 1 and parts[0] == 'inet':
            return parts[1]
    return None


if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--job', help='job id', default=None)
    parser.add_argument('action', help='action to perform', choices=['list', 'start', 'remove'])
    cmd_args = parser.parse_args()

    all_jobs = {}
    for filename in glob.glob("%s*.json" % JOB_DIR):
        all_jobs[os.path.basename(filename).split('.')[0]] = filename

    if cmd_args.action == 'list':
        result['status'] = 'ok'
        result['jobs'] = []
        for jobid in all_jobs:
            try:
                started = os.path.getmtime(all_jobs[jobid])
            except FileNotFoundError:
                # removed while listing
                continue
            job = load_json(all_jobs[jobid]) or {}
            job['id'] = jobid
            job['started'] = datetime.fromtimestamp(started).isoformat(timespec='seconds')
            job['sent'] = job['received'] = job['error'] = ''
            if len(job_pids(jobid)) > 0:
                job['status'] = 'running'
            else:
                output = load_json("%s%s.log" % (JOB_DIR, jobid))
                if output is None:
                    job['status'] = 'error'
                    job['error'] = 'no result, the test was stopped after %s seconds' % (
                        int(job.get('duration', 0)) + GRACE_TIME
                    )
                elif output.get('error'):
                    job['status'] = 'error'
                    job['error'] = output['error']
                else:
                    job['status'] = 'done'
                    job['sent'] = round(output['end']['sum_sent']['bits_per_second'] / 1000000, 2)
                    job['received'] = round(output['end']['sum_received']['bits_per_second'] / 1000000, 2)
            result['jobs'].append(job)
    elif cmd_args.action == 'start' and cmd_args.job in all_jobs:
        settings = load_json(all_jobs[cmd_args.job]) or {}
        logfile = "%s%s.log" % (JOB_DIR, cmd_args.job)
        args = [
            '/usr/sbin/daemon', '-f',
            '/usr/bin/timeout', '-s', 'KILL', str(int(settings.get('duration', 10)) + GRACE_TIME),
            '/usr/local/bin/iperf3', '-J',
            '-c', settings.get('server', ''),
            '-p', settings.get('port', '5201'),
            '-t', settings.get('duration', '10'),
            '-P', settings.get('parallel', '1'),
            '--logfile', logfile
        ]
        if settings.get('protocol') == 'udp':
            args.append('-u')
        if settings.get('reverse') == '1':
            args.append('-R')
        result['status'] = 'ok'
        if settings.get('interface', '') != '':
            address = interface_address(settings['interface'])
            if address is None:
                result['status'] = 'failed'
                result['status_msg'] = 'no IPv4 address on %s' % settings['interface']
            else:
                args += ['-B', address]
        if len(job_pids(cmd_args.job)) > 0:
            result['status'] = 'failed'
            result['status_msg'] = 'already running'
        if result['status'] == 'ok':
            if os.path.exists(logfile):
                os.remove(logfile)
            if subprocess.run(args).returncode != 0:
                result['status'] = 'failed'
                result['status_msg'] = 'unable to start iperf3'
    elif cmd_args.action == 'remove' and cmd_args.job in all_jobs:
        result['status'] = 'ok'
        for pid in job_pids(cmd_args.job):
            subprocess.run(['kill', pid])
        for filename in glob.glob("%s%s*" % (JOB_DIR, cmd_args.job)):
            os.remove(filename)
    else:
        result['status'] = 'failed'

    print(ujson.dumps(result))
