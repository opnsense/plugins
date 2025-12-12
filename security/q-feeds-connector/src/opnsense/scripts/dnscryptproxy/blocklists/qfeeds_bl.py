#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Deciso B.V.
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
import configparser

cnf = configparser.ConfigParser()
cnf.read('/usr/local/etc/qfeeds-dnscryptproxy-bl.conf')

qfeeds_filenames = []
if cnf.has_section('settings'):
    if cnf.has_option('settings', 'filenames'):
        qfeeds_filenames = cnf.get('settings', 'filenames').split(',')

# Collect q-feeds domains
qfeeds_domains = set()
for filename in qfeeds_filenames:
    if os.path.exists(filename):
        with open(filename, 'r') as f_in:
            for line in f_in:
                domain = line.strip()
                if domain:
                    qfeeds_domains.add(domain)

# Write q-feeds domains to blacklist-qfeeds.txt
# dnscrypt-proxy's dnsbl.sh will automatically merge blacklist-*.txt files
qfeeds_blacklist_file = '/usr/local/etc/dnscrypt-proxy/blacklist-qfeeds.txt'

os.makedirs('/usr/local/etc/dnscrypt-proxy', exist_ok=True)

if qfeeds_domains:
    # Write q-feeds domains to separate file
    with open(qfeeds_blacklist_file, 'w') as f_out:
        for domain in sorted(qfeeds_domains):
            f_out.write("%s\n" % domain)
else:
    # Remove q-feeds blacklist file if disabled
    if os.path.exists(qfeeds_blacklist_file):
        os.remove(qfeeds_blacklist_file)

