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
import glob
import re

# Check if 'qf' is selected in DNSCrypt-proxy DNSBL configuration
def is_qf_selected():
    rc_conf_file = '/etc/rc.conf.d/dnscrypt_proxy'
    if os.path.exists(rc_conf_file):
        try:
            with open(rc_conf_file, 'r') as f:
                for line in f:
                    # Look for dnscrypt_proxy_dnsbl="..." line
                    match = re.search(r'dnscrypt_proxy_dnsbl="([^"]*)"', line)
                    if match:
                        dnsbl_list = match.group(1)
                        # Check if 'qf' is in the comma-separated list
                        return 'qf' in [x.strip() for x in dnsbl_list.split(',')]
        except Exception:
            pass
    return False

# Q-Feeds domain files directory
qfeeds_tables_dir = '/var/db/qfeeds-tables'

# Automatically find all domain files (*_domains.txt) in qfeeds-tables directory
# This will include malware_domains.txt, phishing_domains.txt, etc.
qfeeds_filenames = []
if os.path.isdir(qfeeds_tables_dir):
    # Find all files ending with _domains.txt (e.g., malware_domains.txt, phishing_domains.txt)
    pattern = os.path.join(qfeeds_tables_dir, '*_domains.txt')
    qfeeds_filenames = sorted(glob.glob(pattern))

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
# dnscrypt-proxy's dnsbl.sh qfeeds() function will read this file when 'qf' is selected in DNSBL config
qfeeds_blacklist_file = '/usr/local/etc/dnscrypt-proxy/blacklist-qfeeds.txt'
dnscrypt_proxy_dir = '/usr/local/etc/dnscrypt-proxy'

# Only proceed if DNSCrypt-proxy directory exists (plugin is installed) AND 'qf' is selected
if os.path.isdir(dnscrypt_proxy_dir) and is_qf_selected():
	if qfeeds_domains:
		# Write q-feeds domains to separate file
		with open(qfeeds_blacklist_file, 'w') as f_out:
			for domain in sorted(qfeeds_domains):
				f_out.write("%s\n" % domain)
	else:
		# Remove q-feeds blacklist file if no domains available
		if os.path.exists(qfeeds_blacklist_file):
			os.remove(qfeeds_blacklist_file)
elif os.path.isdir(dnscrypt_proxy_dir):
	# DNSCrypt-proxy is installed but 'qf' is not selected - remove the file if it exists
	if os.path.exists(qfeeds_blacklist_file):
		os.remove(qfeeds_blacklist_file)
