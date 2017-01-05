#!/bin/sh

ACME_DIRS="/var/etc/acme-client /var/etc/acme-client/certs /var/etc/acme-client/keys /var/etc/acme-client/configs /var/etc/acme-client/challenges /var/etc/acme-client/home"

for directory in ${ACME_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 755 ${directory}
done

# XXX: fix file permissions of rc script (limitation of +TARGETS mechanism)
chmod 755 /usr/local/etc/rc.d/acme_http_challenge

exit 0
