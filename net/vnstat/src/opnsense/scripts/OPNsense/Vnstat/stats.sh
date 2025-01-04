#!/bin/sh

type="$1"
interface="$2"

if [ -z "$interface" ]; then
	# Interface not specified, use default interface for backward compatibility
	interface=`awk '/^Interface/ { print $2 }' < /usr/local/etc/vnstat.conf`
fi

vnstat -$type $interface
