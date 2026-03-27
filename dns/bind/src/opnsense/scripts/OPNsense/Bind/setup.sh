#!/bin/sh

for DIR in /var/run/named /var/dump /var/stats /var/log/named /usr/local/etc/namedb/primary; do
	mkdir -p ${DIR}
	chown -R bind:bind ${DIR}
	chmod 755 ${DIR}
done

# This should help clean out orphaned journal files
if ! rndc sync -clean ; then
	# If the RNDC command didn't work, we should probably clean
	# the files out manually because on a clean shutdown they
	# would be cleared out by "service named stop" ... so if
	# they're still around it means something went down HARD and
	# thus the files are suspect and could derail BIND9 startup
	find /usr/local/etc/namedb/primary -type f -name '*.jnl' -print -delete
fi
