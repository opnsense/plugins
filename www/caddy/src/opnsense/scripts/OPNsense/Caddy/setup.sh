#!/bin/sh

# Define directories
CADDY_DIR="/usr/local/etc/caddy"
CADDY_CERTS_DIR="/var/db/caddy/data/caddy/certificates/temp"
CADDY_LOG_DIR="/var/log/caddy/access"
CADDY_CONF_DIR="${CADDY_DIR}/caddy.d"

# Create custom directories with appropriate permissions
mkdir -p "${CADDY_CERTS_DIR}"
chown -R root:wheel "${CADDY_CERTS_DIR}"
chmod -R 600 "${CADDY_CERTS_DIR}"

mkdir -p "${CADDY_LOG_DIR}"
chown -R root:wheel "${CADDY_LOG_DIR}"
chmod -R 750 "${CADDY_LOG_DIR}"

mkdir -p "${CADDY_CONF_DIR}"
chown -R root:wheel "${CADDY_CONF_DIR}"
chmod -R 750 "${CADDY_CONF_DIR}"

# Format and overwrite the Caddyfile
(cd "${CADDY_DIR}" && /usr/local/bin/caddy fmt --overwrite)

# Write custom certs from the OPNsense Trust Store into a directory where Caddy can read them
/usr/local/opnsense/scripts/OPNsense/Caddy/caddy_certs.php
