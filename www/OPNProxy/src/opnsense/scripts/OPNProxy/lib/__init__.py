"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
import copy
import tarfile
import os
import stat
import syslog
import time
import requests
from configparser import ConfigParser


class Policy:
    def __init__(self, policy_filename):
        self._policy_config = policy_filename
        self._domain_entries = dict()
        self._policy_settings = dict()
        self._tf = None
        self.load()

    def load(self):
        """ load policy database
        :return:
        """
        self._domain_entries = dict()
        self._policy_settings = dict()
        # collect all policies per domain, so we can safely overwrite existing content when it exists
        cnf = ConfigParser()
        cnf.read(self._policy_config)
        if cnf.has_section('source'):
            blocklist_filename = cnf.get('source', 'blocklist')
            if cnf.has_option('source', 'blocklist_download_uri'):
                blocklist_ttl = cnf.getint('source', 'blocklist_ttl')
                if not os.path.isfile(blocklist_filename) or \
                        time.time() - os.stat(blocklist_filename)[stat.ST_MTIME] > blocklist_ttl:
                    try:
                        response = requests.get(cnf.get('source', 'blocklist_download_uri'), stream=True)
                        response.raise_for_status()
                        with open(blocklist_filename, 'wb') as handle:
                            for block in response.iter_content(1024):
                                handle.write(block)
                    except requests.exceptions.RequestException as e:
                        # we are unable to download a new blocklist, if a previous version still exists keep using that
                        syslog.syslog(syslog.LOG_ERR, 'unable to download new blocklist (%s)' % e)

            if os.path.isfile(blocklist_filename) and tarfile.is_tarfile(blocklist_filename):
                self._tf = tarfile.open(fileobj=open(blocklist_filename, "rb"))
            else:
                syslog.syslog(syslog.LOG_ERR, 'default policy rules not available (%s missing)' % blocklist_filename)

        for section in cnf.sections():
            if cnf.has_option(section, 'policy_type') and cnf.has_option(section, 'content'):
                self._policy_settings[section] = {
                    'action': cnf.get(section, 'action'),
                    'id': section.split('_', 1)[-1],
                    'applies_on': cnf.get(section, 'applies_on').split(','),
                    'source_net': cnf.get(section, 'source_net').split(','),
                    'policy_type': cnf.get(section, 'policy_type'),
                    'description': cnf.get(section, 'description')
                }
                ittr_method = self._itr_default if cnf.get(section, 'policy_type') == "default" else self._itr_custom
                split_char = ',' if cnf.get(section, 'policy_type') == "default" else '\n'
                for is_wildcard, item in ittr_method(cnf.get(section, 'content').split(split_char)):
                    parts = item.split('/', 1)
                    domain = parts[0]
                    if domain not in self._domain_entries:
                        self._domain_entries[domain] = list()
                    self._domain_entries[domain].append([
                        section,
                        "/%s" % parts[1] if len(parts) > 1 else "/",
                        is_wildcard
                    ])

    def _itr_default(self, items: list):
        if self._tf:
            for tf_file in self._tf.getmembers():
                if tf_file.isreg():
                    fhandle = self._tf.extractfile(tf_file)
                    if tf_file.name.count('/') >= 2 and tf_file.name.split('/')[-2] in items:
                        filename = os.path.basename(tf_file.name)
                        if filename in ['urls', 'domains']:
                            for line in fhandle.read().decode().split('\n'):
                                line = line.strip()
                                if line:
                                    # assume domains are wildcards (e.g. youtube.com --> .youtube.com)
                                    yield line.find('/') == -1, line

    @staticmethod
    def _itr_custom(items: list):
        for line in items:
            if line.startswith('.') or line.startswith('*'):
                # wildcard search, e.g. matches all subdomains of given domain, where * is the absolute toplevel (root)
                yield True, line.lstrip('.')
            else:
                yield False, line

    def __iter__(self):
        for domain in self._domain_entries:
            # prepare domain policies
            policy = {
                'domain': domain,
                'items': []
            }
            for entry in self._domain_entries[domain]:
                politem = copy.deepcopy(self._policy_settings[entry[0]])
                politem['path'] = entry[1]
                politem['wildcard'] = entry[2]
                policy['items'].append(politem)
            yield policy

    def exists(self, domain):
        return domain.split(':')[-1] in self._domain_entries
