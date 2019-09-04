#!/bin/sh

ACME_DIRS="/var/etc/acme-client /var/etc/acme-client/certs /var/etc/acme-client/keys /var/etc/acme-client/configs /var/etc/acme-client/challenges /var/etc/acme-client/home"

for directory in ${ACME_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 750 ${directory}
done

if [ ! -L /var/etc/acme-client/home/dns_opnsense.sh ]; then
    ln -s /usr/local/opnsense/scripts/OPNsense/AcmeClient/dns_opnsense.sh /var/etc/acme-client/home/dns_opnsense.sh
fi

exit 0
