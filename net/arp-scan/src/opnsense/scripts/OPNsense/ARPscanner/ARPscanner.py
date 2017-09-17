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
import json
import re
#~ from IPtools import get_ip_address

_DEBUG=False

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

class ArpScanner(object):
    def __init__(self, ifname, network_list):
        """
        netif='eth0'
        network_list=['192.168.0.0/24', ...]
        """
        self.ifname         = ifname
        self.network_list   = network_list if network_list else RFC1918_NETWORKS
        self.outputs        = {} # raw outputs from system command
        self.result         = {} # filtered output containing only what needed
        
    def start(self):
        self.result['networks'] = {}
        for net in self.network_list:
            os_command = ["arp-scan", "-I", self.ifname, net]
            self.outputs[net] = Popen(os_command, stdin=PIPE, stdout=PIPE, stderr=PIPE)
            output, err = self.outputs[net].communicate()
            returncode = self.outputs[net].returncode
            if _DEBUG: print(os_command, returncode, output, err)
            self.outputs[net] = returncode, output, err
            
            regexp = re.findall('([0-9\.]+)[\t]*([\dA-F]{2}(?:[-:][\dA-F]{2}){5})[\t]*([A-Za-z0-9\ \.\-\,\'\(\)]*)', output, re.I)
            if _DEBUG: print(regexp)
            for netfound in regexp:
                if not self.result['networks'].get(net): self.result['networks'][net] = []
                self.result['networks'][net].append((netfound[0].replace('\t', ''), netfound[1], netfound[2], net.replace('-','')))
                
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
      
    # This is the correct way to handle accepting multiple arguments.
    # '+' == 1 or more.
    # '*' == 0 or more.
    # '?' == 0 or 1.
    # An int is an explicit number of arguments to accept.
    parser.add_argument('-i', nargs='?', required=True, help="network interface")
    parser.add_argument('-r', nargs='+', help="""multiple network ranges,
    as: 192.168.1.0/24 172.16.31.0/12
    If not specified it will scan all the RFC 1918 local area networks.""")
    args = parser.parse_args()
    
    if not args.r[0]:
        args.r = ['--localnet']
    elif len(args.r) == 1:
        args.r = args.r[0].split(',')

    #~ print("Scan interface: {}".format(args.i))    
    #~ plural = '' if len(args.i) == 1 else 's'
    #~ print('Network{} to scan: {}'.format(plural, ' '.join(args.r)))
    
    ap = ArpScanner(args.i, args.r)
    ap.start()
    print(ap.get_json())
