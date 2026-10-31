#!/bin/sh

pw groupmod -n dialer -m nut

mkdir -p /var/db/nut
chown -R nut:nut /var/db/nut

# The template engine gives generated files the directory's mode (0644), but
# these hold the NUT user passwords. upsd runs as nut, so keep group read.
for FILE in upsd.conf upsd.users upsmon.conf; do
	if [ -f /usr/local/etc/nut/${FILE} ]; then
		chown root:nut /usr/local/etc/nut/${FILE}
		chmod 0640 /usr/local/etc/nut/${FILE}
	fi
done
