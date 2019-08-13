#!/bin/sh

ACME_BASE="/var/etc/acme-client"
ACME_DIRS="/var/etc/acme-client/certs /var/etc/acme-client/keys /var/etc/acme-client/configs /var/etc/acme-client/challenges /var/etc/acme-client/home"

# Generating dirs if missing and setting owner and mode (recursively)
for directory in ${ACME_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 750 ${directory}
done

# Setting owner and mode for base and immediate children (non recursive)
chown root:wheel ${ACME_BASE} ${ACME_BASE}/*
chmod 750 ${ACME_BASE} ${ACME_BASE}/*

exit 0
