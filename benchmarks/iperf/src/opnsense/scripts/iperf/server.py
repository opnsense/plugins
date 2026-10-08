#!/usr/local/bin/python3

"""
Copyright (C) 2026 Cedrik Pischem
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
import fcntl
import glob
import json
import os
import socket
import subprocess
import time
import uuid
from datetime import datetime


JOB_DIR = '/tmp/iperf-server'
STARTUP_GRACE = 5


def job_path(job_id, extension):
    return os.path.join(JOB_DIR, '%s.%s' % (job_id, extension))


def load_json(filename):
    try:
        with open(filename, 'r') as handle:
            return json.load(handle)
    except (OSError, ValueError):
        return None


def load_last_json(filename):
    try:
        with open(filename, 'r') as handle:
            content = handle.read()
    except OSError:
        return None
    decoder = json.JSONDecoder()
    result = None
    while content.strip():
        try:
            value, offset = decoder.raw_decode(content.lstrip())
        except json.JSONDecodeError:
            break
        if isinstance(value, dict):
            result = value
        content = content.lstrip()[offset:]
    return result


def startup_pending(filename):
    try:
        return time.time() - os.path.getmtime(filename) < STARTUP_GRACE
    except OSError:
        return False


def job_pids(job_id):
    output = subprocess.run(
        ['/bin/pgrep', '-f', job_path(job_id, 'log')],
        capture_output=True,
        text=True
    ).stdout
    return [pid for pid in output.split() if pid.isdigit()]


def available_port(port):
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as probe:
            probe.bind(('', port))
            return probe.getsockname()[1]
    except OSError:
        return None


def rate(result, field):
    value = result.get('end', {}).get(field, {}).get('bits_per_second')
    return round(value / 1000000, 2) if isinstance(value, (int, float)) else ''


def list_jobs():
    jobs = []
    for filename in glob.glob(os.path.join(JOB_DIR, '*.json')):
        job_id = os.path.basename(filename).split('.')[0]
        job = load_json(filename)
        if job is None:
            continue
        job.update({'id': job_id, 'sent': '', 'received': '', 'error': ''})
        result = load_last_json(job_path(job_id, 'log'))
        running = bool(job_pids(job_id))
        if running or (result is None and startup_pending(filename)):
            job['status'] = 'running'
        elif result is None:
            job['status'] = 'error'
            job['error'] = 'iperf3 did not start or produced no result'
        elif result.get('error'):
            job['status'] = 'error'
        else:
            job['status'] = 'done'
        if result is not None and result.get('error'):
            job['error'] = result['error']
        elif result is not None:
            job['sent'] = rate(result, 'sum_sent')
            job['received'] = rate(result, 'sum_received')
        jobs.append(job)
    return {'status': 'ok', 'jobs': jobs}


def start(port):
    port = available_port(port)
    if port is None:
        return {'status': 'error', 'error': 'port is already in use'}
    for filename in glob.glob(os.path.join(JOB_DIR, '*.json')):
        job_id = os.path.basename(filename).split('.')[0]
        job = load_json(filename) or {}
        result = load_last_json(job_path(job_id, 'log'))
        if job.get('port') == port and (job_pids(job_id) or (result is None and startup_pending(filename))):
            return {'status': 'error', 'error': 'port is already in use'}

    job_id = uuid.uuid4().hex
    metadata = {
        'started': datetime.now().astimezone().isoformat(timespec='seconds'),
        'port': port
    }
    with open(job_path(job_id, 'json'), 'w') as handle:
        json.dump(metadata, handle)
    command = [
        '/usr/sbin/daemon', '-f',
        '/usr/local/bin/iperf3', '-J', '--forceflush', '-f', 'M', '-s',
        '-p', str(port), '--logfile', job_path(job_id, 'log')
    ]
    result = subprocess.run(command, capture_output=True, text=True)
    if result.returncode != 0:
        os.remove(job_path(job_id, 'json'))
        return {'status': 'error', 'error': result.stderr.strip() or 'unable to start iperf3'}
    return {'status': 'ok', 'id': job_id, 'port': port}


def remove(job_id):
    try:
        if uuid.UUID(job_id).hex != job_id:
            raise ValueError
    except ValueError:
        return {'status': 'error', 'error': 'invalid instance'}
    metadata = job_path(job_id, 'json')
    if not os.path.exists(metadata):
        return {'status': 'error', 'error': 'instance not found'}
    for pid in job_pids(job_id):
        subprocess.run(['/bin/kill', pid])
    for filename in glob.glob(os.path.join(JOB_DIR, '%s.*' % job_id)):
        os.remove(filename)
    return {'status': 'ok'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--job')
    parser.add_argument('--port', type=int, default=0)
    parser.add_argument('action', choices=['list', 'start', 'remove'])
    args = parser.parse_args()
    os.makedirs(JOB_DIR, exist_ok=True)
    with open(os.path.join(JOB_DIR, '.lock'), 'w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        if args.action == 'list':
            response = list_jobs()
        elif args.action == 'start':
            response = start(args.port)
        else:
            response = remove(args.job or '')
    print(json.dumps(response))
