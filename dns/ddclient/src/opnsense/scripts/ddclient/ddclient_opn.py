#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
import sys
import json
from lib import AccountFactory, Poller
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize


if __name__ == '__main__':
    # handle parameters
    parser = argparse.ArgumentParser()
    parser.add_argument('-c', '--config', help='config file [json]', default='/usr/local/etc/ddclient.json')
    parser.add_argument('-s', '--status', help='status output file [json]', default='/var/tmp/ddclient_opn.status')
    parser.add_argument('-f', '--foreground', help='run (log) in foreground', default=False, action='store_true')
    parser.add_argument('-l', '--list', help='list known services and exit', default=False, action='store_true')
    parser.add_argument('-p', '--pid', help='pid file location', default='/var/run/ddclient_opn.pid')
    inputargs = parser.parse_args()
    if inputargs.list:
        print(json.dumps(AccountFactory().known_services()))
    else:
        cmd = lambda : Poller(inputargs.config, inputargs.status)
        daemon = Daemonize(app="ddclient", pid=inputargs.pid, action=cmd, foreground=inputargs.foreground)
        daemon.start()
