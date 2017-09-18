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
import os.path

class FileIO(object):
    """
        This class manages files where data is read
        cname is the .current file, where API object is stored
        lname is the .last file, where the last scan is stored
        oname is the .out file, where os_command stdout and stderr is stored
        
        I'd like to generalize name and objects with lopps over list BUT
        this class was made to keep all things as readble as possibile!
    """
    def __init__(self, name, path):
        # file names
        self.cname = '{}.current'.format(name)
        self.lname = '{}.last'.format(name)
        self.oname = '{}.out'.format(name)
        
        # file paths
        self.cpath = os.path.sep.join((path, self.cname))
        self.lpath = os.path.sep.join((path, self.lname))
        self.opath = os.path.sep.join((path, self.oname))
        
        if not os.path.exists(path):
            os.makedirs(path)
        
        # file obj
        self.current = open(self.cpath, 'w')
        self.last    = open(self.lpath, 'w')
        self.output  = open(self.opath, 'w')
    
    def close(self):
        self.current.close()
        self.last.close()
        self.output.close()
