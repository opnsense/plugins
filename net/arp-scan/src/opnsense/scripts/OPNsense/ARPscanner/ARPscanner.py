
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
import os.path
import json
import sys
import re

# imports from custom cls
from ProcessIO import ProcessIO
#~ from IPtools import get_ip_address

RFC1918_NETWORKS = ["192.168.0.0/16",
                    "172.16.0.0/16",
                    "172.26.0.0/16",
                    "172.27.0.0/16",
                    "172.17.0.0/16",
                    "172.18.0.0/16",
                    "172.19.0.0/16",
                    "172.20.0.0/16",
                    "172.21.0.0/16",
                    "172.22.0.0/16",
                    "172.23.0.0/16",
                    "172.24.0.0/16",
                    "172.25.0.0/16",
                    "172.28.0.0/16",
                    "172.29.0.0/16",
                    "172.30.0.0/16",
                    "172.31.0.0/16",
                    "10.0.0.0/8"]

class ArpScanner(ProcessIO):
    os_command_filter = """ps ax | grep "ARPscanner\|arp-scan -I" | grep {} | grep -E '^[ 0-9]+'| awk -F' ' '{{print $1}}'"""
    
    def __init__(self, ifname, network_list, background=False):
        """
        netif='eth0'
        network_list=['192.168.0.0/24', ...]
        """
        self.ifname         = ifname
        self.network_list   = network_list if network_list else RFC1918_NETWORKS
        self.outputs        = {} # raw outputs from system command
        self.result         = {} # filtered output containing only what needed
        # regexp used to retrieve data from arp-scan system command stdout
        self.regexp =  '([0-9\.]+)[\t]*([\dA-F]{2}(?:[-:][\dA-F]{2}){5})[\t]*([A-Za-z0-9\ \.\-\,\'\(\)]*)'
        # os_command_filter needs '{}'.format(ifname)
        self.mode   = background
        self.tmp    = '/tmp/ARPscanner'
        self._DEBUG = False
    
    @staticmethod
    def status(ifname):
        """
            read from .current 
        """
        # check_run
        # return (returncode, output, err)
        pass
        
    def prepare_start(self, ifname):
        # check if tmpfolder/$ifname.current exists
        fname = '{}.current'.format(ifname)
        lname = '{}.last'.format(ifname)
        fpath = os.path.sep.join((self.tmp, fname))
        lpath = os.path.sep.join((self.tmp, lname))
        if not os.path.exists(self.tmp):
            os.makedirs(self.tmp)
        if not os.path.isfile(fpath):
            pass
        # read PID attribute of .current
        # if PID exists: 
        #    check if it is not dead: move .current to .last
        #    return (returncode, output, err) from .last if not .current
        #
        # else: 
        #    write the .current status file 
        pass
    
    def run_command(self, os_command, background=False):
        """
           os_command: (list) command to run 
           background: .current and .last file are used for I/O
        """
        osc = Popen(os_command, 
                      stdin=PIPE, 
                      stdout=PIPE, 
                      stderr=PIPE)
        
        if background: 
            # run a child and detach
            # write json object on every stdout LINE on the file
            pass
        else:
            # wait for stdout
            output, err = osc.communicate()
            returncode = osc.returncode
            if self._DEBUG: print(os_command, returncode, output, err)
        
        return returncode, output, err
    
    def start(self):
        self.result['networks'] = {}
        for net in self.network_list:
            os_command = ["arp-scan", "-I", self.ifname, net]
            # prepare_start(self.ifname
            self.outputs[net] = self.run_command(os_command, self.mode)
            #~ self.outputs[net] = returncode, output, err
            regexp = re.findall(self.regexp , self.outputs[net][1], re.I)
            if self._DEBUG: print(regexp)
            for netfound in regexp:
                if not self.result['networks'].get(net): 
                    self.result['networks'][net] = []
                self.result['networks'][net].append(
                    (netfound[0].replace('\t', ''), 
                     netfound[1], netfound[2], net.replace('-','')))
                
        self.result['interface'] = self.ifname
        self.result['datetime']  = datetime.datetime.now().isoformat()
    
    def view_outputs(self):
        for res in self.outputs:
            print(res)
            print('return code: {}\n'.format(self.outputs[res][0]))
            print(self.outputs[res][1])
    
    def get_json(self):
        return json.dumps(self.result)
    
if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('-i', nargs='?', required=True, 
                        help="network interface")
    parser.add_argument('-r', nargs='+', help="""multiple network ranges,
    as: 192.168.1.0/24 172.16.31.0/12
    If not specified it will scan all the RFC 1918 local area networks.""")
    parser.add_argument('-d', action="store_true", required=False, 
                        help="background api mode")
    parser.add_argument('-check', action="store_true", required=False, 
                        help="check if arp-san is running on that interface")
    parser.add_argument('-stop', action="store_true", required=False, 
                        help="Stops scanning on that interfaces")

    args = parser.parse_args()
    
    if args.r is None or len(args.r[0]) == 0:
        args.r = ['--localnet',]
    else:
        args.r = args.r[0].split(',')
    
    #~ print("Scan interface: {}".format(args.i))    
    #~ plural = '' if len(args.i) == 1 else 's'
    #~ print('Network{} to scan: {}'.format(plural, ' '.join(args.r)))
    
    if args.check:
        sys.exit(ArpScanner.check_run(args.i, ArpScanner.os_command_filter))

    if args.stop:
        sys.exit(ArpScanner.stop(args.i, ArpScanner.os_command_filter))

    # if args.d -> background run
    ap = ArpScanner(args.i, args.r, 1 if args.d else 0)
    # ap.check_run()
    ap.start()
    print(ap.get_json())
