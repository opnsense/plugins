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
from os import kill, getpid, linesep

_DEBUG = False

class ProcessIO(object):
    @staticmethod
    def check_run(ifname, os_command_filter):
        """
           returns PID if running on that ifname
           else return 0
        """
        mypid = getpid()
        os_command = os_command_filter.format(ifname)
        osc = Popen(os_command,
                      stdin=PIPE,
                      stdout=PIPE,
                      stderr=PIPE,
                      shell=True)
        output, err = osc.communicate()
        if _DEBUG: print(output)
        if output:
            pids = []
            for pid in output.split(linesep):
                if pid and int(pid) != mypid:
                    pids.append(int(pid))
            return pids[:]
        return 0

    @classmethod
    def stop(cls, ifname, os_command_filter):
        """ stop scanning on that interface """
        mypid = getpid()
        chkrun = cls.check_run(ifname, os_command_filter)
        if not chkrun: return []
        pids = [int(i) for i in chkrun]
        killed = []
        for pid in pids:
            if pid == mypid: continue
            try:
                kill(pid, 9)
                killed.append(pid)
            except Exception as e:
                pass
        return killed
