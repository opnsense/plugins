#!/bin/sh
# mpd5 iface down-script: <interface> <proto> <local-ip> <remote-ip> <authname> <peer-address>
# flush states owned by the session so traffic stops immediately

/usr/bin/logger -t pppoe -p local3.info "logout,${1},${4},${5}"

/sbin/pfctl -i "${1}" -Fs 2>/dev/null
/sbin/pfctl -K "${4}/32" 2>/dev/null

exit 0
