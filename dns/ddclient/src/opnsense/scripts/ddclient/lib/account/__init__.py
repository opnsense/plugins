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
import syslog
import hashlib
import uuid
import time
from ..address import checkip


class BaseAccount:
    _priority = 255

    def __init__(self, account: dict):
        self._account = account
        self._account['id'] = account.get('id', str(uuid.uuid4()))
        self._account['description'] = account.get('description', '')
        self._state = {}
        self._last_accessed = 0
        self._current_address = None   # last resolved address

        # calculate a hash so we can easily detect configuration changes
        hash_list = []
        for fieldname in sorted(account.keys()):
            if fieldname not in ['id', 'description', 'checkip', 'checkip_timeout', 'force_ssl']:
                hash_list.append(str(account[fieldname]))
        self._account['md5'] = hashlib.md5("|".join(hash_list).encode()).hexdigest()


    def update_state(self, address, status='good'):
        """ set ip[v4 or v6] address and update in state dict when address is provided.
        """
        if address is not None:
            self._state['ip'] = address
            self._state['status'] = status
            self._state['mtime'] = time.time()
            self._state['md5'] = self.md5
        self._last_accessed = time.time()

    @staticmethod
    def known_services():
        return []

    @property
    def id(self):
        """ account unique id
        """
        return self._account['id']

    @property
    def settings(self):
        return self._account

    @staticmethod
    def match(account):
        """ Does this account fit for the provided specification
        """
        return False

    @property
    def description(self):
        return ("%(id)s [%(service)s - %(description)s] " % self._account)

    @property
    def state(self):
        return self._state

    @state.setter
    def state(self, value: dict):
        self._state = value

    @property
    def mtime(self):
        return self._state.get('mtime', 0)

    @property
    def atime(self):
        return self._last_accessed

    @property
    def md5(self):
        return self._account.get('md5')

    @property
    def is_verbose(self):
        return self._account.get('verbose') is True

    @property
    def current_address(self):
        return self._current_address

    def execute(self):
        """ execute account check/update sequence, return true if state changed
        """
        self._current_address = checkip(
            service = self.settings.get('checkip'),
            proto = 'https' if self.settings.get('force_ssl', False) else 'http',
            timeout = str(self.settings.get('checkip_timeout', '10')),
            interface = self.settings['interface'] if self.settings.get('interface' ,'').strip() != '' else None
        )

        if self._current_address == None:
            syslog.syslog(
                syslog.LOG_WARNING,
                "Account %s no global IP address detected, check config if warning persists" % (self.description)
            )
            return False
        elif (
                self._state.get('ip') is None or
                self._current_address != self._state.get('ip') or
                self.state.get('md5') != self.md5
        ):
            # if current address doesn't equal the current state, propagate the fact
            return True
        else:
            # unmodified, poller keeps track of last access timestamp
            return False
