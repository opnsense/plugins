#!/bin/sh

# Define directories
CADDY_CERTS_DIR="/var/db/caddy/data/caddy/certificates/temp"
CADDY_LOG_DIR="/var/log/caddy/access"
CADDY_CONF_DIR="/usr/local/etc/caddy/caddy.d"

# Create custom directories with appropriate permissions
mkdir -p "$CADDY_CERTS_DIR"
chown -R root:wheel "$CADDY_CERTS_DIR"
chmod -R 750 "$CADDY_CERTS_DIR"

mkdir -p "$CADDY_LOG_DIR"
chown -R root:wheel "$CADDY_LOG_DIR"
chmod -R 750 "$CADDY_LOG_DIR"

mkdir -p "$CADDY_CONF_DIR"
chown -R root:wheel "$CADDY_CONF_DIR"
chmod -R 750 "$CADDY_CONF_DIR"

# Format and overwrite the Caddyfile
(cd "/usr/local/etc/caddy" && /usr/local/bin/caddy fmt --overwrite)

# Write custom certs from the OPNsense Trust Store into a directory where Caddy can read them
/usr/local/opnsense/scripts/OPNsense/Caddy/caddy_certs.php

