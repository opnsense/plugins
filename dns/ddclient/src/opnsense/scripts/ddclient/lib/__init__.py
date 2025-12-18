"""
    Copyright (c) 2022-2023 Ad Schellevis <ad@opnsense.org>
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

    -------------------------------------------------------------------------------------------------------
    OPNsense ddclient alternative version.

    The base for the application is a configuration file /usr/local/etc/ddclient.json containing a `general` and
    `account` section. Structured dictionaries per account settings determine the services to subscribe to,
    general settings contain verbosity settings and poll intervals for example.

    Package structure:
        * address.py
            - contains all logic to figure out which address to use.
        * poller.py
            - The main poller class, which drives dyndns resolving.
        * accounts
            - Account/service type definitions, deriving from `BaseAccount`.

    The BaseAccount type:

        The lifetime of an account starts with the determination if an account object matches a requested configuration
        account. Our AccountFactory() class is responsible for figuring our which classes are available and would
        fit a provided definition using the `match(account)` method.

        Every account has an `atime` property, which determines when was the last time we compared if the stored address
        matches the requested one and if needed was set accordingly at the remote service.

        Since every account likely has a dependency on an ip address, the base acccount implements an `execute()` method
        which detects basic change (address, configuration changes) after which the implementation can do the actual
        work and report if the address has really changed. This saves code and keeps the implementation simpler.

    The Poller class:

        Upon creation will start reading the configuration and merges the last known state (json), after each poll where something
        changed (return status of `execute()`) the state is flushed to disk.

"""
from .address import checkip_service_list, checkip
from .poller import AccountFactory, Poller
