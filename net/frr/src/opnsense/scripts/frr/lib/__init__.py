"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import time
import subprocess
import ujson
from collections.abc import Callable


class InterfaceStatus:
    def __init__(self):
        self._carp_addresses = dict()
        self.parse()

    def parse(self):
        """ parse ifconfig output
        """
        carp_statuses = dict()
        carp_addresses = dict()
        current_if = None
        for line in subprocess.run(['/sbin/ifconfig', '-a'], capture_output=True, text=True).stdout.split('\n'):
            parts = line.split()
            if not line.startswith('\t'):
                current_if = line.split(':')[0]
            elif line.startswith('\tcarp: '):
                carp_statuses[parts[3]] = parts[1]
            elif line.find('vhid') > -1:
                carp_addresses[parts[1]] = {'vhid': parts[-1], 'status': 'none'}

        for address in carp_addresses:
            if carp_addresses[address]['vhid'] in carp_statuses:
                carp_addresses[address]['status'] = carp_statuses[carp_addresses[address]['vhid']].strip().lower()

        self._carp_addresses = carp_addresses

    def address_status(self, address: str):
        if address in self._carp_addresses:
            return self._carp_addresses[address]['status']
        return 'none'


class VtySHExecError(Exception):
    pass

class VtySH:
    def __init__(self):
        self._daemons = []
        self.init()

    def init(self):
        # wait a maximum of 5 seconds for daemon to startup
        for i in range(5):
            try:
                self._daemons = self.execute('show daemons', lambda x: x.decode().split())
                break
            except VtySHExecError:
                time.sleep(1)

    def is_running(self, daemon: str):
        return daemon in self._daemons

    @property
    def is_active(self):
        return len(self._daemons) > 0

    def execute(self, command: str, translate: Callable=ujson.loads, configure: bool=False):
        args = ['/usr/local/bin/vtysh']
        if configure:
            args = args + ['-c', 'configure terminal']
        else:
            args.append('-u')

        if type(command) is list:
            for cmd in command:
                args = args + ['-c', cmd]
        else:
            args = args + ['-c', command]

        response = subprocess.run(args, capture_output=True)
        if response.stderr:
            raise VtySHExecError(response.stderr)
        if translate:
            try:
                return translate(response.stdout)
            except ValueError:
                raise ValueError(response.stdout)
        else:
            return response.stdout
