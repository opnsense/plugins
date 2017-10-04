#!/usr/bin/env python2.7

"""
    Copyright (c) 2017 Giuseppe De Marco <giuseppe.demarco@unical.it>
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
from subprocess import Popen, PIPE
import datetime
import os
import time
import json
import sys
import re

# imports from custom cls
from ProcessIO import ProcessIO
from FileIO import FileIO

class ArpScanner(ProcessIO):
    os_command_filter = """ps ax | \
                           grep "ARPscanner\|arp-scan -I" | \
                           grep {} | \
                           grep -v "ps ax "| \
                           grep -E '^[ 0-9]+'| \
                           awk -F' ' '{{print $1}}'"""

    def __init__(self, ifname, network):
        """
        netif='eth0'
        network_list=['192.168.0.0/24', ...]
        """
        self.ifname         = ifname
        self.network        = network
        self.result         = {} # filtered output containing only what needed
        # regexp used to retrieve data from arp-scan system command stdout
        self.regexp =  '([0-9\.]+)[\t]*([\dA-F]{2}(?:[-:][\dA-F]{2}){5})[\t]*([A-Za-z0-9\ \.\-\,\'\(\)]*)'
        # os_command_filter needs '{}'.format(ifname)
        self._DEBUG = False

        # FileIO contains all the IO files
        tmp_fileio_path   = '/tmp/ARPscanner'
        self.tmp    = tmp_fileio_path

        self.result['peers'] = []
        self.result['network'] = network
        self.result['interface'] = self.ifname
        self.result['started']  = datetime.datetime.now().isoformat()
        self.result['last_modify']  = datetime.datetime.now().isoformat()

    def status(self):
        """
            if arp-scan is running: parse .current file
            else: parse .last file
            returns json parsing of arp-scan output
        """
        fout = os.path.sep.join((self.tmp, self.ifname))+'.out'
        if not os.path.exists(fout):
            return

        self.result['last'] = time.ctime(os.path.getmtime(fout))
        self.result['started'] = time.ctime(os.path.getctime(fout))

        with open(fout, 'r') as f:
            fcont = f.read()
            #~ print(fcont)
            regexp = re.findall(self.regexp , fcont, re.I)
            if self._DEBUG: print(regexp)
            for netfound in regexp:
                self.result['peers'].append(
                    (netfound[0].replace('\t', ''),
                     netfound[1], netfound[2]))
        #~ return self.result

    def start(self):
        """
            returns 1 if started
            returns  0 if already running
        """
        running = self.check_run(self.ifname, self.os_command_filter)
        if running: return self.status()

        fileio = FileIO(self.ifname, self.tmp)
        os_command = ["arp-scan", "-I", self.ifname, self.network,
                      "--retry", "5"]
        # run a child and detach
        osc = Popen(os_command,
                    stdout=fileio.out,
                    stderr=fileio.err,
                    bufsize=0,
                    shell=False)

    def get_json(self):
        return json.dumps(self.result)

if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('-i', nargs='?', required=True,
                        help="network interface")
    parser.add_argument('-net', nargs='?', help="""network to scan""")
    parser.add_argument('-check', action="store_true", required=False,
                        help="check if arp-san is running on that interface")
    parser.add_argument('-start', action="store_true", required=False,
                        help="starts arp-scan")
    parser.add_argument('-stop', action="store_true", required=False,
                        help="Stops scanning on that interfaces")
    parser.add_argument('-status', action="store_true", required=False,
                        help="Parse arp-scan stdout and return json")

    args = parser.parse_args()

    if not args.net:
        args.net = '--localnet'

    if args.check:
        pids = ArpScanner.check_run(args.i, ArpScanner.os_command_filter)
        print(pids)
        sys.exit()

    if args.stop:
        killed = ArpScanner.stop(args.i, ArpScanner.os_command_filter)
        print(killed)
        sys.exit()

    ap = ArpScanner(args.i, args.net)

    if args.start:
        ap.start()

    ap.status()
    print(ap.get_json())
