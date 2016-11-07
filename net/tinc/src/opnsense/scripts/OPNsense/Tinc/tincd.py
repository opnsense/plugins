#!/usr/local/bin/python2.7

"""
    Copyright (c) 2016 Deciso B.V. - Ad Schellevis
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

    --------------------------------------------------------------------------------------
    reconfigure tincd, using the supplied configuration
"""
import os
import tempfile
import glob
import xml.etree.ElementTree
from lib import objects

def write_file(filename, content):
    dirname = '/'.join(filename.split('/')[0:-1])
    if not os.path.isdir(dirname):
        os.makedirs(dirname)
    open(filename, 'w').write(content)

def deploy(config_filename):
    # collect file info
    config_files=dict()
    if os.path.isfile(config_filename):
        for network in xml.etree.ElementTree.parse(config_filename).getroot():
            Network_obj = objects.Network()
            for network_prop in network:
                Network_obj.set(network_prop.tag, network_prop)
            # check if config is complete before collecting output files
            if Network_obj.is_valid():
                for conf_obj in Network_obj.all():
                    if conf_obj.is_valid():
                        config_files[conf_obj.filename()] = conf_obj.config_text()
                # private key
                tmp = Network_obj.privkey()
                config_files[tmp['filename']] = tmp['content']
    # remove previous configuration
    os.system('rm -rf /usr/local/etc/tinc')
    # write output
    for filename in config_files:
        write_file(filename, config_files[filename])

deploy('/usr/local/etc/tinc_deploy.xml')
