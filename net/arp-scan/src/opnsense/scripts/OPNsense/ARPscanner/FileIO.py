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
import os

class FileIO(object):
    """
        This class manages files where data is read
        .out and .err file, where os_command stdout and stderr is stored
    """
    def __init__(self, name, path):
        # file names
        self.nerr = '{}.err'.format(name)
        self.nout = '{}.out'.format(name)

        # file paths
        self.epath = os.path.sep.join((path, self.nerr))
        self.opath = os.path.sep.join((path, self.nout))

        if not os.path.exists(path):
            os.makedirs(path)

        # file obj, 1 means the buffer size, small as possibile to flush
        # data soon as possible :)
        # this feature would be better with python3
        self.err  = open(self.epath, 'w', buffering=0)
        self.out  = open(self.opath, 'w', buffering=0)


    def close(self):
        self.err.close()
        self.out.close()
