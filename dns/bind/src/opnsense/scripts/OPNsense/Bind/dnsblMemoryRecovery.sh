#!/bin/sh
# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


# A DNSBL/RPZ load can have a large temporary allocation peak. If the startup
# memory guard stops named, keep DNS available by persisting DNSBL-off, then
# reloading the template and starting named without the RPZ zone.

NAMED_RECOVERY_DISABLE=${NAMED_RECOVERY_DISABLE:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblDisableOnMemoryGuard.php}
NAMED_RECOVERY_CONFIGCTL=${NAMED_RECOVERY_CONFIGCTL:-configctl}
NAMED_RECOVERY_LOGGER=${NAMED_RECOVERY_LOGGER:-logger}
NAMED_RECOVERY_NAMED_RC=${NAMED_RECOVERY_NAMED_RC:-/usr/local/etc/rc.d/named}
selected_codes=${1:-}

"${NAMED_RECOVERY_DISABLE}" "${selected_codes}"
disable_status=$?
if [ "${disable_status}" -eq 2 ]; then
    "${NAMED_RECOVERY_LOGGER}" -p daemon.crit -t named \
        "DNSBL startup memory guard will not overwrite a newer DNSBL selection; named remains stopped."
    exit 2
fi

if [ "${disable_status}" -ne 0 ]; then
    "${NAMED_RECOVERY_LOGGER}" -p daemon.crit -t named \
        "DNSBL startup memory guard could not disable DNSBL in the BIND configuration; named remains stopped."
    exit 1
fi

if ! "${NAMED_RECOVERY_CONFIGCTL}" template reload OPNsense/Bind; then
    "${NAMED_RECOVERY_LOGGER}" -p daemon.crit -t named \
        "DNSBL startup memory guard disabled DNSBL but could not reload the BIND template; named remains stopped."
    exit 1
fi

if ! "${NAMED_RECOVERY_NAMED_RC}" start; then
    "${NAMED_RECOVERY_LOGGER}" -p daemon.crit -t named \
        "DNSBL startup memory guard disabled DNSBL but could not restart named without RPZ."
    exit 1
fi

"${NAMED_RECOVERY_LOGGER}" -p daemon.crit -t named \
    "DNSBL startup memory guard disabled DNSBL in the BIND configuration and restarted named without RPZ."
