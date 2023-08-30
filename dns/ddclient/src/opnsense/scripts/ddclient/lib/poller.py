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
import fcntl
import syslog
import glob
import importlib
import sys
import os
import time
import ujson
import ipaddress
from .account import BaseAccount


class AccountFactory:
    def __init__(self):
        self._account_classes = list()
        self._register()

    def _register(self):
        """ Register all account (type) classes.
            These usually describe a protocol (like dyndns2)
        """
        pkg_name = "%s.account" % __name__[:-len(os.path.splitext(os.path.basename(__file__))[0])-1]
        all_account_classes = list()
        for filename in glob.glob("%s/account/*.py" % os.path.dirname(__file__)):
            importlib.import_module(".%s" % os.path.splitext(os.path.basename(filename))[0], pkg_name)

        for module_name in dir(sys.modules[pkg_name]):
            for attribute_name in dir(getattr(sys.modules[pkg_name], module_name)):
                cls = getattr(getattr(sys.modules[pkg_name], module_name), attribute_name)
                if isinstance(cls, type) and issubclass(cls, BaseAccount) and cls != BaseAccount:
                    all_account_classes.append(cls)

        self._account_classes = sorted(all_account_classes, key=lambda k: k._priority)

    def get(self, account: dict):
        for handler in self._account_classes:
            if handler.match(account):
                return handler(account)

    def known_services(self):
        all_services = []
        for handler in self._account_classes:
            all_services += handler.known_services()
        return all_services


class Poller:
    def __init__(self, config_filename, status_filename):
        self._config_filename = config_filename
        self._status_filename = status_filename
        self._accounts = {}
        self._general_settings = {}
        syslog.openlog('ddclient', facility=syslog.LOG_LOCAL4)
        self.startup()
        self.run()

    @property
    def is_verbose(self):
        return self._general_settings.get('verbose') is True

    @property
    def is_enabled(self):
        return self._general_settings.get('enabled') is True

    @property
    def poll_interval(self):
        return self._general_settings.get('daemon_delay', 60)

    def startup(self):
        account_factory = AccountFactory()
        with open(self._config_filename) as f:
            cnf = ujson.load(f)
            if type(cnf.get('general')) is dict:
                self._general_settings = cnf.get('general')
            if type(cnf.get('accounts')) is list:
                for account in cnf.get('accounts'):
                    account['verbose'] = self.is_verbose
                    acc = account_factory.get(account)
                    if acc:
                        self._accounts[acc.id] = acc
                        if self.is_verbose:
                            syslog.syslog(
                                syslog.LOG_NOTICE,
                                "Account %s uses %s for service" % (acc.description, acc.__class__.__name__)
                            )
                    elif self.is_verbose:
                        syslog.syslog(
                            syslog.LOG_NOTICE,
                            "Unable to find a suitable target for account %(id)s [%(description)s]" % account
                        )
        if len(self._accounts) > 0 and os.path.isfile(self._status_filename):
            with open(self._status_filename) as f:
                try:
                    state = ujson.load(f)
                    if type(state) is dict:
                        for sid in state:
                            if sid in self._accounts:
                                self._accounts[sid].state = state[sid]
                except ValueError:
                    syslog.syslog(syslog.LOG_ERR, "Unable to read file %s" % self._status_filename)

    def flush_status(self):
        fhandle = open(self._status_filename, 'a+')
        try:
            fcntl.flock(fhandle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            syslog.syslog(syslog.LOG_ERR, "Unable to flush status, %s already locked" % self._status_filename)
            return
        fhandle.seek(0)
        fhandle.truncate()
        data = {}
        for acc_id in self._accounts:
            data[acc_id] = self._accounts[acc_id].state
        fhandle.write(ujson.dumps(data))
        fhandle.close()

    def run(self):
        while True:
            needs_flush = False
            for acc in self._accounts.values():
                if time.time() - acc.atime > self.poll_interval:
                    if self.is_verbose:
                        syslog.syslog(syslog.LOG_NOTICE, "Account %s executing" % acc.description)
                    try:
                        if acc.execute():
                            if self.is_verbose:
                                syslog.syslog(syslog.LOG_NOTICE, "Account %s updated" % acc.description)
                            needs_flush = True
                        else:
                            if self.is_verbose:
                                syslog.syslog(syslog.LOG_NOTICE, "Account %s not modified" % acc.description)
                            # update last accessed timestamp
                            acc.update_state(None)
                    except Exception as e:
                        # fatal exception, update atime so we're not going to retry too soon
                        acc.update_state(None)
                        syslog.syslog(syslog.LOG_ERR, "Account %s raised fatal error (%s)" % (acc.description, e))

            if needs_flush:
                if self.is_verbose:
                    syslog.syslog(syslog.LOG_NOTICE, "Flush dyndns status to disk")
                self.flush_status()

            # XXX: needs better poll interval calculation
            time.sleep(5)
