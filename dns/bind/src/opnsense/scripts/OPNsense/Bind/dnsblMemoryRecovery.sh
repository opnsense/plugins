#!/bin/sh

# A DNSBL/RPZ load can have a large temporary allocation peak. If the startup
# memory guard stops named, keep DNS available by persisting DNSBL-off, then
# reloading the template and starting named without the RPZ zone.

NAMED_RECOVERY_DISABLE=${NAMED_RECOVERY_DISABLE:-/usr/local/opnsense/scripts/OPNsense/Bind/dnsblDisableOnMemoryGuard.php}
NAMED_RECOVERY_CONFIGCTL=${NAMED_RECOVERY_CONFIGCTL:-configctl}
NAMED_RECOVERY_LOGGER=${NAMED_RECOVERY_LOGGER:-logger}
NAMED_RECOVERY_NAMED_RC=${NAMED_RECOVERY_NAMED_RC:-/usr/local/etc/rc.d/named}

if ! "${NAMED_RECOVERY_DISABLE}"; then
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
