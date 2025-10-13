"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
    Copyright (c) 2024 Olly Baker <ilumos@gmail.com>
    Copyright (c) 2025 Oliver Traber <hi@bluemedia.dev>
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
import requests
from . import BaseAccount


class PowerDNS(BaseAccount):

    def __init__(self, account: dict):
        super().__init__(account)
        # min TTL set to 300
        self.settings['ttl'] = max(int(self.settings["ttl"]) if self.settings.get("ttl", "").isdigit() else 0, 300)

    @staticmethod
    def known_services():
        return {"powerdns": "PowerDNS API"}

    @staticmethod
    def match(account):
        return account.get("service") in ['powerdns']


    def _send_request(self, method, url, params=None, json=None):
        headers = {
            "User-Agent": "OPNsense-dyndns",
            "X-API-Key": self.settings.get("password"),
        }

        base_url = "%s/api/v1/servers/%s" % (
            self.settings.get('server'),
            self.settings.get("server_id", "localhost")
        )

        url = base_url + url
        return requests.request(method=method, url=url, headers=headers, params=params, json=json)


    def _find_zone_id(self, hostname):
        # Get the zone that a record belongs to
        if self.is_verbose:
            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s trying to get zone ID for hostname %s"
                % (self.description, hostname),
            )

        zone = hostname
        while (True):
            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s checking if zone %s exists"
                    % (self.description, zone),
                )

            response = self._send_request(method="GET", url="/zones", params={"zone": zone})

            if response.status_code == 200:
                try:
                    payload = response.json()
                    # Check if a zone was found
                    if len(payload) == 0:
                        # Move one up in hierarchy
                        zone = '.'.join(zone.split('.')[1:])
                        # Fail if root is reached
                        if zone == "":
                            syslog.syslog(
                                syslog.LOG_ERR,
                                "Account %s error getting zone ID for hostname %s - No matching zone found on server"
                                % (self.description, hostname),
                            )
                            return None
                        else:
                            continue
                    else:
                        if self.is_verbose:
                            syslog.syslog(
                                syslog.LOG_NOTICE,
                                "Account %s found zone %s for hostname %s"
                                % (self.description, zone, hostname),
                            )
                        return payload[0].get("id")
                except requests.exceptions.JSONDecodeError:
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error getting zone ID for hostname %s - Failed to decode response as JSON. Response: %s"
                        % (self.description, hostname, response.text),
                    )
                    return None
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s error getting zone ID for hostname %s HTTP %s. Response: %s"
                    % (
                        self.description,
                        hostname,
                        response.status_code,
                        response.text,
                    ),
                )
                return None

    def _replace_rrset(self, hostname, zone_id, record_type, content):
        # Replace or create rrset for record
        payload = {
            "rrsets": [
                {
                    "name": hostname,
                    "type": record_type,
                    "ttl": int(self.settings.get("ttl")),
                    "changetype": "REPLACE",
                    "records": [
                        {"content": content}
                    ]
                }
            ]
        }

        response = self._send_request(method="PATCH", url=("/zones/" + zone_id), json=payload)
        if response.status_code == 204:
            # Success
            return True
        else:
            # Failure
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error updating hostname %s in zone %s - HTTP %s Response: %s"
                % (
                    self.description,
                    hostname,
                    zone_id,
                    response.status_code,
                    response.text,
                ),
            )
            return False

    def execute(self):
        if super().execute():
            record_type = "AAAA" if str(self.current_address).find(":") > 1 else "A"

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s current IP is %s (%s)"
                    % (self.description, self.current_address, record_type),
                )

                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s updating hostnames %s"
                    % (self.description, self.settings.get("hostnames", "")),
                )

            # Update each hostname
            for hostname in self.settings.get("hostnames", "").split(","):

                # Make hostname absolute
                if not hostname.endswith("."):
                    hostname = hostname + "."

                # Get id of zone the hostname belongs to
                zone_id = self._find_zone_id(hostname)

                # If zone can't be found, skip
                if zone_id == None:
                    continue

                # Update record set
                if self._replace_rrset(hostname, zone_id, record_type, content=self.current_address) and self.is_verbose:
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "Account %s successfully updated hostname %s (%s) to IP %s"
                        % (
                            self.description,
                            hostname,
                            record_type,
                            self.current_address,
                        ),
                    )
            self.update_state(address=self.current_address)
            return True
        return False
