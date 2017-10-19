#!/bin/sh

RADIUS_FILES="/var/log/radius.log /var/log/radutmp /var/log/radwtmp"
RADIUS_DIRS="/usr/local/etc/raddb /var/run/radiusd /var/log/radacct"
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

# clear old certificates and export new ones
rm -f /usr/local/etc/raddb/certs/ca_*.pem
rm -f /usr/local/etc/raddb/certs/cert_*.pem
/usr/local/opnsense/scripts/Freeradius/generate_certs.php > /dev/null 2>&1
/usr/local/opnsense/scripts/Freeradius/generate_crl.php > /dev/null 2>&1
