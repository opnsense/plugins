#!/bin/sh

ACME_DIRS="/var/etc/acme-client /var/etc/acme-client/certs /var/etc/acme-client/keys /var/etc/acme-client/configs /var/etc/acme-client/home"

for directory in ${ACME_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 755 ${directory}
done

CHALLENGES_DIR="/var/etc/acme-client/challenges"

mkdir -p ${CHALLENGES_DIR}
chown -R www:www ${CHALLENGES_DIR}
chmod -R 755 ${CHALLENGES_DIR}

exit 0
