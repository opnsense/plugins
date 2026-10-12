#!/bin/sh
# mpd5 iface up-script: <interface> <proto> <local-ip> <remote-ip> <authname> ...
# keep clients in a dedicated pf interface group and log the event

/usr/bin/logger -t pppoe -p local3.info "login,${1},${4},${5}"
/sbin/ifconfig "${1}" group pppoe

exit 0
