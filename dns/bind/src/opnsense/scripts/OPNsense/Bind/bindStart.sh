#!/bin/sh

BIND_START_NAMED_RC=${BIND_START_NAMED_RC:-/usr/local/etc/rc.d/named}
BIND_START_RC_CONF=${BIND_START_RC_CONF:-/etc/rc.conf.d/named}
BIND_START_PGREP=${BIND_START_PGREP:-pgrep}
BIND_START_STATUS=${BIND_START_STATUS:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblStatus.py}
BIND_START_GUARD=${BIND_START_GUARD:-/usr/local/opnsense/scripts/OPNsense/Bind/namedMemoryGuard.sh}
BIND_START_DHCPWATCHER=${BIND_START_DHCPWATCHER:-/usr/local/opnsense/scripts/OPNsense/Bind/dhcpwatcherStart.sh}

dnsbl_enabled()
{
    grep -q '^named_dnsbl="[^"]' "${BIND_START_RC_CONF}" 2>/dev/null
}

if ! "${BIND_START_NAMED_RC}" status >/dev/null 2>&1; then
    "${BIND_START_NAMED_RC}" start || exit $?
fi

if dnsbl_enabled; then
    named_pid=$("${BIND_START_PGREP}" -o named) || exit 1
    "${BIND_START_STATUS}" starting "BIND is loading DNSBL/RPZ; monitoring Memory Guard."
    "${BIND_START_GUARD}" "${named_pid}" &
else
    previous_stage=$("${BIND_START_STATUS}" --stage)
    if [ "${previous_stage}" != "guard_recovered" ]; then
        "${BIND_START_STATUS}" disabled "DNSBL/RPZ is disabled."
    fi
fi

"${BIND_START_DHCPWATCHER}"
