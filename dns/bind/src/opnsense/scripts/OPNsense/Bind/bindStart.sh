#!/bin/sh

/usr/local/etc/rc.d/named start || exit $?
/usr/local/opnsense/scripts/OPNsense/Bind/dhcpwatcherStart.sh
