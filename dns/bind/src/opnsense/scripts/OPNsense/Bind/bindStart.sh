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


BIND_START_NAMED_RC=${BIND_START_NAMED_RC:-/usr/local/etc/rc.d/named}
BIND_START_RC_CONF=${BIND_START_RC_CONF:-/etc/rc.conf.d/named}
BIND_START_PGREP=${BIND_START_PGREP:-pgrep}
BIND_START_STATUS=${BIND_START_STATUS:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblStatus.py}
BIND_START_GUARD=${BIND_START_GUARD:-/usr/local/opnsense/scripts/OPNsense/Bind/namedMemoryGuard.py}

dnsbl_codes()
{
    sed -n 's/^named_dnsbl="\([^"]*\)"$/\1/p' "${BIND_START_RC_CONF}" | head -n 1
}

case "${1:-start}" in
    start|restart)
        action="$1"
        ;;
    *)
        echo "usage: $0 {start|restart}" >&2
        exit 64
        ;;
esac

"${BIND_START_NAMED_RC}" "${action}" || exit $?

selected_codes=$(dnsbl_codes)
if [ -n "${selected_codes}" ]; then
    named_pid=$("${BIND_START_PGREP}" -o named) || exit 1
    "${BIND_START_STATUS}" starting "BIND is loading DNSBL/RPZ; monitoring Memory Guard."
    "${BIND_START_GUARD}" "${named_pid}" "${selected_codes}" </dev/null >/dev/null 2>&1 &
else
    previous_stage=$("${BIND_START_STATUS}" --stage)
    if [ "${previous_stage}" != "guard_recovered" ]; then
        "${BIND_START_STATUS}" disabled "DNSBL/RPZ is disabled."
    fi
fi
