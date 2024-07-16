#!/bin/sh

#
# Copyright (c) 2023-2024 Cedrik Pischem
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification,
# are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.
#

# The directories are created as root:www with rwx permissions for both,
# so the user can change in the GUI if caddy runs as root or www
# If only ports 1024 and above are used, caddy can run as www user and group.

# Define directories
CADDY_CONF_DIR="/usr/local/etc/caddy"
CADDY_DATA_DIR="/var/db/caddy"
CADDY_LOG_DIR="/var/log/caddy"
CADDY_RUN_DIR="/var/run/caddy"
CADDY_CONF_CUSTOM_DIR="${CADDY_CONF_DIR}/caddy.d"
CADDY_DATA_CUSTOM_DIR="${CADDY_DATA_DIR}/data/caddy/certificates/temp"
CADDY_LOG_CUSTOM_DIR="${CADDY_LOG_DIR}/access"

mkdir -p "${CADDY_CONF_DIR}"
mkdir -p "${CADDY_DATA_DIR}"
mkdir -p "${CADDY_LOG_DIR}"
mkdir -p "${CADDY_RUN_DIR}"
mkdir -p "${CADDY_CONF_CUSTOM_DIR}"
mkdir -p "${CADDY_DATA_CUSTOM_DIR}"
mkdir -p "${CADDY_LOG_CUSTOM_DIR}"

chown -R root:www "${CADDY_CONF_DIR}"
chown -R root:www "${CADDY_DATA_DIR}"
chown -R root:www "${CADDY_LOG_DIR}"
chown -R root:www "${CADDY_RUN_DIR}"

# Directories need execute permissions to be accessible
find "${CADDY_CONF_DIR}" -type d -exec chmod 770 {} +
find "${CADDY_DATA_DIR}" -type d -exec chmod 770 {} +
find "${CADDY_LOG_DIR}" -type d -exec chmod 770 {} +
find "${CADDY_RUN_DIR}" -type d -exec chmod 770 {} +

# Files can have read/write permissions
find "${CADDY_CONF_DIR}" -type f -exec chmod 660 {} +
find "${CADDY_DATA_DIR}" -type f -exec chmod 660 {} +
find "${CADDY_LOG_DIR}" -type f -exec chmod 660 {} +
find "${CADDY_RUN_DIR}" -type f -exec chmod 660 {} +

# Format and overwrite the Caddyfile, this makes whitespace control in jinja2 unnecessary
(cd "${CADDY_CONF_DIR}" && /usr/local/bin/caddy fmt --overwrite)

# Write custom certs from the OPNsense Trust Store to CADDY_DATA_CUSTOM_DIR
/usr/local/opnsense/scripts/OPNsense/Caddy/caddy_certs.php
