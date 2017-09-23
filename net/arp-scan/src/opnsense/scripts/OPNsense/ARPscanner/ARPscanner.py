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
from FileIO import FileIO
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
    os_command_filter = """ps ax | \
                           grep "ARPscanner\|arp-scan -I" | \
                           grep {} | \
                           grep -v "ps ax "| \
                           grep -E '^[ 0-9]+'| \
                           awk -F' ' '{{print $1}}'"""
    
    def __init__(self, ifname, network_list, background=False):
        """
        netif='eth0'
        network_list=['192.168.0.0/24', ...]
        """
        self.ifname         = ifname
        self.network_list   = network_list if network_list else RFC1918_NETWORKS
        self.result         = {} # filtered output containing only what needed
        # regexp used to retrieve data from arp-scan system command stdout
        self.regexp =  '([0-9\.]+)[\t]*([\dA-F]{2}(?:[-:][\dA-F]{2}){5})[\t]*([A-Za-z0-9\ \.\-\,\'\(\)]*)'
        # os_command_filter needs '{}'.format(ifname)
        self.mode   = background
        self._DEBUG = False
        
        self.background = background
        # FileIO contains all the IO files
        tmp_fileio_path   = '/tmp/ARPscanner'
        self.tmp    = tmp_fileio_path
        self.fileio = FileIO(ifname, tmp_fileio_path)
        
    
    def status(self, ifname):
        """
            read from .current 
        """
        # check_run
        # return (returncode, output, err)
        # parse json object on every stdout LINE found in the output file 
        pass
    
    def run_command(self, os_command, background=False):
        """
           os_command: (list) command to run 
           background: .current .last .out file are used for I/O
        """          
        # deprecated      
        pass

    def prepare_start(self, ifname):
        """
            fname is the .current file, where API object is stored
            lname is the .last file, where the last scan is stored
            oname is the .out file, where os_command stdout and stderr is stored
        """
        # WIP:
        # check if tmpfolder/$ifname.current exists
        if not os.path.isfile(fpath):
            # this means that there's no .current execution
            pass
            
        # read PID attribute of .current
        # if PID exists: 
        #    check if it is not dead: move .current to .last
        #    return (returncode, output, err) from .last if not .current
        #
        # else: 
        #    write the .current status file 
    
    def start(self):
        """
            self.result is the dict that will store the obj to dump in json
        """
        # WIP: file I/O logic 
        self.result['networks'] = {}
        self.result['interface'] = self.ifname
        self.result['datetime']  = datetime.datetime.now().isoformat()
        for net in self.network_list:
            os_command = ["arp-scan", "-I", self.ifname, net]
            if self.background: 
                err = 0
                # run a child and detach
                osc = Popen(os_command, 
                  stdout=self.fileio.output, # stdout and stderr on the same 
                  stderr=self.fileio.output)
            else:
                osc = Popen(os_command, 
                  stdin=PIPE, 
                  stdout=PIPE, 
                  stderr=PIPE)   

            # wait for stdout
            output, err = osc.communicate()
            returncode = osc.returncode
            if self._DEBUG: print(os_command, returncode, output, err)
            # self.outputs[net][1] is the stdout of the command
            regexp = re.findall(self.regexp , output, re.I)
            if self._DEBUG: print(regexp)
            for netfound in regexp:
                if not self.result['networks'].get(net): 
                    self.result['networks'][net] = []
                self.result['networks'][net].append(
                    (netfound[0].replace('\t', ''), 
                     netfound[1], netfound[2], net.replace('-','')))
        
        return returncode, output, err
        # self.fileio.close()
    
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
    
    # try/except produced me "Execute error" in configd execution... 
    # is it strange?
    if args.check:
        pids = ArpScanner.check_run(args.i, ArpScanner.os_command_filter)
        print(pids)
        sys.exit()

    if args.stop:
        killed = ArpScanner.stop(args.i, ArpScanner.os_command_filter)
        print(killed)
        sys.exit()

    # if args.d -> background run
    ap = ArpScanner(args.i, args.r, 1 if args.d else 0)
    ap.start()
    print(ap.get_json())
