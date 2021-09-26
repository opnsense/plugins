#!/usr/local/bin/python3
# -*- coding: utf-8 -*-

"""
    Copyright (c) 2014-2019 Ad Schellevis <ad@opnsense.org>
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

    package : Dnscryptproxy
    function: returns contents of a request pre-defined configuration file
"""

import sys
import os.path

args = sys.argv

CONF_DIR = '/usr/local/etc/dnscrypt-proxy'

# Predefined list of configuraiton files allowed to be viewed.
ALLOWED_FILES = [
    'allowed-ips-internal.txt',
    'allowed-ips-manual.txt',
    'allowed-names-internal.txt',
    'allowed-names-manual.txt',
    'blocked-ips-internal.txt',
    'blocked-ips-manual.txt',
    'blocked-names-internal.txt',
    'blocked-names-manual.txt',
    'captive-portals.txt',
    'cloaking-internal.txt',
    'cloaking-manual.txt',
    'dnscrypt-proxy.toml',
    'forwarding-rules.txt',
    'local_doh-cert.pem',
    'local_doh-cert_key.pem',
    'public-resolvers.md',
    'relays.md',
]


def main():
    """ Main function for starting the script.
    """
    # Only do this if we have exactly 1 argument passed with the script.
    if len(args) == 2:
        # If the requested file is in the allowed files list then
        # set the file name, check it exists, and if so, print
        # its contents.
        if args[1] in ALLOWED_FILES:
            fname = CONF_DIR + '/' + args[1]
            if os.path.exists(fname):
                with open(fname, 'r') as fin:
                    print(fin.read())


if __name__ == '__main__':
    main()
