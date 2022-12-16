#!/bin/sh

if [ -f /etc/rc.conf.d/wireguard ]; then
	. /etc/rc.conf.d/wireguard
fi

for interface in ${wireguard_interfaces}; do
	ifconfig ${interface} group wireguard
done

/usr/local/etc/rc.routing_configure
