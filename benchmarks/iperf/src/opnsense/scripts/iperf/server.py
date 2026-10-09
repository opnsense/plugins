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
import base64
import binascii
import fcntl
import glob
import json
import os
import socket
import subprocess
import time
import uuid
from datetime import datetime


JOB_DIR = '/var/db/iperf/server'
STARTUP_GRACE = 5


def job_path(job_id, extension):
    return os.path.join(JOB_DIR, '%s.%s' % (job_id, extension))


def load_json(filename):
    try:
        with open(filename, 'r') as handle:
            return json.load(handle)
    except (OSError, ValueError):
        return None


def load_job(job_id):
    try:
        if uuid.UUID(job_id).hex != job_id:
            return None
    except ValueError:
        return None
    return load_json(job_path(job_id, 'json'))


def load_events(filename):
    events = []
    try:
        with open(filename, 'r') as handle:
            for line in handle:
                try:
                    event = json.loads(line)
                    if isinstance(event, dict):
                        events.append(event)
                except json.JSONDecodeError:
                    continue
    except OSError:
        pass
    return events


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


def stop_processes(job_id):
    for pid in job_pids(job_id):
        subprocess.run(['/bin/kill', pid])
    for _ in range(20):
        if not job_pids(job_id):
            break
        time.sleep(0.1)
    return not job_pids(job_id)


def available_port(port):
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as probe:
            probe.bind(('', port))
            return probe.getsockname()[1]
    except OSError:
        return None


def rate(result, field):
    value = result.get(field, {}).get('bits_per_second')
    return value if isinstance(value, (int, float)) else ''


def failed(error, **details):
    return {'status': 'failed', 'error': error, **details}


def list_jobs():
    jobs = []
    for filename in glob.glob(os.path.join(JOB_DIR, '*.json')):
        job_id = os.path.basename(filename).split('.')[0]
        job = load_json(filename)
        if job is None:
            continue
        job.update({'id': job_id, 'sent': '', 'received': '', 'error': ''})
        events = load_events(job_path(job_id, 'log'))
        last_event = events[-1] if events else {}
        last_result = next(
            (event.get('data', {}) for event in reversed(events) if event.get('event') == 'end'),
            None
        )
        running = bool(job_pids(job_id))
        if running:
            active = last_event.get('event') in ('start', 'interval')
            job['status'] = 'running' if active else 'listening'
        elif os.path.exists(job_path(job_id, 'stop')):
            job['status'] = 'stopped'
        elif last_event.get('event') == 'error':
            job['status'] = 'error'
        elif not events and startup_pending(filename):
            job['status'] = 'listening'
        else:
            job['status'] = 'stopped'
        if last_result is not None:
            job['sent'] = rate(last_result, 'sum_sent')
            job['received'] = rate(last_result, 'sum_received')
        if last_event.get('event') == 'error':
            error = last_event.get('data')
            if error is not None:
                job['error'] = error.get('error', str(error)) if isinstance(error, dict) else str(error)
        jobs.append(job)
    return {'status': 'ok', 'jobs': jobs}


def launch(job_id, port):
    logfile = job_path(job_id, 'log')
    if os.path.exists(logfile):
        os.remove(logfile)
    command = [
        '/usr/sbin/daemon', '-f',
        '/usr/local/bin/iperf3', '--json-stream', '--forceflush', '-f', 'M', '-s',
        '-p', str(port), '--logfile', logfile
    ]
    result = subprocess.run(command, capture_output=True, text=True)
    if result.returncode != 0:
        return failed('start_failed', detail=result.stderr.strip())
    metadata = load_job(job_id)
    metadata['started'] = datetime.now().astimezone().isoformat(timespec='seconds')
    with open(job_path(job_id, 'json'), 'w') as handle:
        json.dump(metadata, handle)
    stopped = job_path(job_id, 'stop')
    if os.path.exists(stopped):
        os.remove(stopped)
    return {'status': 'ok', 'id': job_id, 'port': port}


def create(settings):
    try:
        port = int(settings.get('port', 0))
    except (TypeError, ValueError):
        return failed('invalid_settings')
    port = available_port(port)
    if port is None:
        return failed('in_use')
    for filename in glob.glob(os.path.join(JOB_DIR, '*.json')):
        job_id = os.path.basename(filename).split('.')[0]
        job = load_json(filename) or {}
        stopped = os.path.exists(job_path(job_id, 'stop'))
        if job.get('port') == port and not stopped and (job_pids(job_id) or startup_pending(filename)):
            return failed('in_use')

    job_id = uuid.uuid4().hex
    metadata = dict(settings)
    metadata['started'] = datetime.now().astimezone().isoformat(timespec='seconds')
    metadata['port'] = port
    with open(job_path(job_id, 'json'), 'w') as handle:
        json.dump(metadata, handle)
    result = launch(job_id, port)
    if result['status'] != 'ok':
        os.remove(job_path(job_id, 'json'))
    return result


def start(job_id):
    job = load_job(job_id)
    if job is None:
        return failed('not_found')
    if job_pids(job_id):
        return failed('in_use')
    if available_port(job['port']) is None:
        return failed('in_use')
    return launch(job_id, job['port'])


def stop(job_id):
    if load_job(job_id) is None:
        return failed('not_found')
    if not stop_processes(job_id):
        return failed('stop_failed')
    with open(job_path(job_id, 'stop'), 'w'):
        pass
    return {'status': 'ok'}


def remove(job_id):
    if load_job(job_id) is None:
        return failed('not_found')
    if not stop_processes(job_id):
        return failed('stop_failed')
    for filename in glob.glob(os.path.join(JOB_DIR, '%s.*' % job_id)):
        os.remove(filename)
    return {'status': 'ok'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--job')
    parser.add_argument('--settings', default='')
    parser.add_argument('action', choices=['list', 'create', 'start', 'stop', 'remove'])
    args = parser.parse_args()
    os.makedirs(JOB_DIR, mode=0o750, exist_ok=True)
    with open(os.path.join(JOB_DIR, '.lock'), 'w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        if args.action == 'list':
            response = list_jobs()
        elif args.action == 'create':
            try:
                settings = json.loads(base64.b64decode(args.settings, validate=True).decode())
                if not isinstance(settings, dict):
                    raise ValueError
            except (binascii.Error, UnicodeDecodeError, ValueError):
                response = failed('invalid_settings')
            else:
                response = create(settings)
        elif args.action == 'start':
            response = start(args.job or '')
        elif args.action == 'stop':
            response = stop(args.job or '')
        else:
            response = remove(args.job or '')
    print(json.dumps(response))
