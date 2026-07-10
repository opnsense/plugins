#!/bin/sh

DNSBL_SCRIPT="/usr/local/opnsense/scripts/OPNsense/Bind/dnsbl.py"

"$DNSBL_SCRIPT" "$@" || exit $?

if /usr/local/etc/rc.d/named status >/dev/null 2>&1; then
    /usr/local/sbin/rndc reload blacklist.localdomain || exit $?
    /usr/local/sbin/rndc flush
fi
