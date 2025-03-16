#!/bin/sh

ACME_BASE="/var/etc/acme-client"
ACME_DIRS="/var/etc/acme-client/certs /var/etc/acme-client/keys /var/etc/acme-client/configs /var/etc/acme-client/challenges /var/etc/acme-client/home /var/etc/acme-client/cert-home"
ACME_LINKS="deploy dnsapi notify"
ACME_LINK_TARGET="/usr/local/share/examples/acme.sh"

# Create required directories and set owner/mode recursively.
for directory in ${ACME_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 750 ${directory}
done

# Set owner/mode for base and immediate children (non recursive).
chown root:wheel ${ACME_BASE} ${ACME_BASE}/*
chmod 750 ${ACME_BASE} ${ACME_BASE}/*

# Create symlinks for acme.sh script directories.
# This should guard against manual misconfiguration.
for link in ${ACME_LINKS}; do
    # First remove any existing file/directory.
    if [ -f "${ACME_BASE}/home/${link}" ]; then
        rm ${ACME_BASE}/home/${link}
    elif [ -d "${ACME_BASE}/home/${link}" ]; then
        rmdir ${ACME_BASE}/home/${link}
    elif [ ! -e "${ACME_BASE}/home/${link}" ]; then
        # Create the symlink.
        ln -s ${ACME_LINK_TARGET}/${link} ${ACME_BASE}/home/${link}
    fi
done

exit 0
