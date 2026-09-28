"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
    Copyright (c) 2024 Olly Baker <ilumos@gmail.com>
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
import json
import syslog
import requests
from . import BaseAccount


class DigitalOcean(BaseAccount):
    _priority = 65535

    _services = {"digitalocean": "api.digitalocean.com"}

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return {"digitalocean": "DigitalOcean"}

    @staticmethod
    def match(account):
        return account.get("service") in DigitalOcean._services

    def execute(self):
        if super().execute():
            recordType = "AAAA" if str(self.current_address).find(":") > 1 else "A"

            headers = {
                "User-Agent": "OPNsense-dyndns",
                "Authorization": "Bearer " + self.settings.get("password"),
            }

            url = "https://%s/v2/domains/%s/records" % (
                self._services[self.settings.get("service")],
                self.settings.get("zone"),
            )

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s current IP is %s (%s)"
                    % (self.description, self.current_address, recordType),
                )

                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s updating hostnames %s"
                    % (self.description, self.settings.get("hostnames", "")),
                )

            # Update each hostname
            for hostname in self.settings.get("hostnames", "").split(","):

                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s getting record ID for hostname %s (%s)"
                        % (self.description, hostname, recordType),
                    )

                request = {
                    "url": url,
                    "params": {"name": hostname, "type": recordType},
                    "headers": headers,
                }

                # Get record ID
                response = requests.get(**request)

                try:
                    payload = response.json()
                except requests.exceptions.JSONDecodeError:
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error getting record ID for hostname %s (%s): failed to decode response as JSON. Response: %s"
                        % (self.description, hostname, recordType, response.text),
                    )
                    continue

                if response.status_code != 200:
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error getting record ID for hostname %s (%s): HTTP %s. Response: %s"
                        % (
                            self.description,
                            hostname,
                            recordType,
                            response.status_code,
                            response.text,
                        ),
                    )
                    continue

                if not payload.get("domain_records"):
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error getting record ID for hostname %s (%s): no results returned. Response: %s"
                        % (self.description, hostname, recordType, response.text),
                    )
                    continue

                record_id = payload["domain_records"][0]["id"]
                if self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s record ID for %s (%s) is %s"
                        % (self.description, hostname, recordType, record_id),
                    )

                request = {
                    "url": "%s/%s" % (url, str(record_id)),
                    "json": {
                        "type": recordType,
                        "data": str(self.current_address),
                    },
                    "headers": headers,
                }

                # Update record IP
                response = requests.patch(**request)

                if response.status_code == 200:
                    if self.is_verbose:
                        syslog.syslog(
                            syslog.LOG_NOTICE,
                            "Account %s successfully updated hostname %s (%s) to IP %s"
                            % (
                                self.description,
                                hostname,
                                recordType,
                                self.current_address,
                            ),
                        )

                else:
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s failed to set new IP (%s) for hostname %s (%s): HTTP %s. Response: %s"
                        % (
                            self.description,
                            self.current_address,
                            self.description,
                            hostname,
                            recordType,
                            response.status_code,
                            response.text,
                        ),
                    )
                    continue
            self.update_state(address=self.current_address)
            return True
        return False
