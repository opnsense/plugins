#!/bin/sh

ACME_BASE="/var/etc/acme-client"
ACME_DIRS="/var/etc/acme-client/certs /var/etc/acme-client/keys /var/etc/acme-client/configs /var/etc/acme-client/challenges /var/etc/acme-client/home /var/etc/acme-client/cert-home"

# Create required directories and set owner/mode recursively.
for directory in ${ACME_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 750 ${directory}
done

# Remove symlink in order to use upstream version
# see https://github.com/opnsense/plugins/pull/1888
if [ -L /var/etc/acme-client/home/dns_opnsense.sh ]; then
    unlink /var/etc/acme-client/home/dns_opnsense.sh
fi

# Set owner/mode for base and immediate children (non recursive).
chown root:wheel ${ACME_BASE} ${ACME_BASE}/*
chmod 750 ${ACME_BASE} ${ACME_BASE}/*

exit 0
