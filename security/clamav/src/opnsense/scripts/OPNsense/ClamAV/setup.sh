#!/bin/sh

USER=clamav
GROUP=clamav
PERMS=0755
DIRS="
/var/db/clamav
/var/run/clamav
/var/log/clamav
"

for DIR in ${DIRS}; do
	if [ -L ${DIR} ]; then
		DIRS="${DIRS} $(realpath ${DIR})"
	fi
done

for DIR in ${DIRS}; do
	mkdir -p ${DIR}
	chown -R ${USER}:${GROUP} ${DIR}
	chmod ${PERMS} ${DIR}
done
