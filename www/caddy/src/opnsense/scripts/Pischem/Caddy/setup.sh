#!/bin/sh

# Define directories
CADDY_DIR="/usr/local/etc/caddy"
CADDY_ACME_DIR="${CADDY_DIR}/acme"
CADDY_CERTS_DIR="${CADDY_DIR}/certificates/temp"
CADDY_OCSP_DIR="${CADDY_DIR}/ocsp"
CADDY_LOCKS_DIR="${CADDY_DIR}/locks"
CADDY_LOG_DIR="/var/log/caddy/access"
CADDY_CONF_DIR="${CADDY_DIR}/caddy.d"
CADDY_CONFIG_DIR="${CADDY_DIR}/.config/caddy" # Additional config directory

# Create Caddy configuration directories with appropriate permissions
mkdir -p "${CADDY_DIR}"
mkdir -p "${CADDY_ACME_DIR}"
mkdir -p "${CADDY_CERTS_DIR}"
mkdir -p "${CADDY_OCSP_DIR}"
mkdir -p "${CADDY_LOCKS_DIR}"
mkdir -p "${CADDY_CONF_DIR}"
mkdir -p "${CADDY_CONFIG_DIR}"

# Set permissions for Caddy configuration directories
chown -R root:wheel "${CADDY_DIR}"
chmod -R 750 "${CADDY_DIR}"

# Create Caddy log directory
mkdir -p "${CADDY_LOG_DIR}"

# Set permissions for Caddy log directory
chown -R root:wheel "${CADDY_LOG_DIR}"
chmod -R 750 "${CADDY_LOG_DIR}"

# Ensure the Caddy service script is executable
chmod +x /usr/local/etc/rc.d/caddy

# Ensure the Caddy binary is executable
chmod +x /usr/local/bin/caddy

# Format and overwrite the Caddyfile
cd "${CADDY_DIR}" && /usr/local/bin/caddy fmt --overwrite

# Write custom certs from the OPNsense Trust Store into a directory where Caddy can read them
/usr/local/opnsense/scripts/Pischem/Caddy/caddy_certs.php

# echo "Caddy installation completed. All caddy directories and files created successfully."
