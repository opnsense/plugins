#!/bin/sh

RADIUS_FILES="/var/log/radius.log /var/log/radutmp /var/log/radwtmp"
RADIUS_DIRS="/usr/local/etc/raddb /var/run/radiusd /var/log/radacct /usr/local/etc/raddb/mods-config/passwd"
RADIUS_USER=freeradius
RADIUS_GROUP=freeradius

for DIR in ${RADIUS_DIRS}; do
	mkdir -p ${DIR}
	chmod -R 750 ${DIR}
	chown -R ${RADIUS_USER}:${RADIUS_GROUP} ${DIR}
done

for FILE in ${RADIUS_FILES}; do
	touch ${FILE}
	chmod 700 ${FILE}
done

/usr/local/opnsense/scripts/Freeradius/generate_certs.php
