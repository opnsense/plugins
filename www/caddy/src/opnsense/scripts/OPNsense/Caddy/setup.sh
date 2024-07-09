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
# depending on the used ports. When >1023 are used, caddy can run as www user and group.

# Define directories
CADDY_DIR="/usr/local/etc/caddy"
CADDY_CERTS_DIR="/var/db/caddy/data/caddy/certificates/temp"
CADDY_LOG_DIR="/var/log/caddy/access"
CADDY_CONF_DIR="${CADDY_DIR}/caddy.d"
CADDY_RUN_DIR="/var/run/caddy"
CADDY_MAIN_LOG_DIR="/var/log/caddy"

# Create custom directories with appropriate permissions
mkdir -p "${CADDY_CERTS_DIR}"
mkdir -p "${CADDY_LOG_DIR}"
mkdir -p "${CADDY_CONF_DIR}"
mkdir -p "${CADDY_RUN_DIR}"
mkdir -p "${CADDY_MAIN_LOG_DIR}"

# Set ownership and permissions for the caddy directories
chown -R root:www "${CADDY_DIR}"
chown -R root:www "${CADDY_CERTS_DIR}"
chown -R root:www "${CADDY_LOG_DIR}"
chown -R root:www "${CADDY_CONF_DIR}"
chown -R root:www "${CADDY_RUN_DIR}"
chown -R root:www "${CADDY_MAIN_LOG_DIR}"

chmod -R 770 "${CADDY_DIR}"
chmod -R 770 "${CADDY_CERTS_DIR}"
chmod -R 770 "${CADDY_LOG_DIR}"
chmod -R 770 "${CADDY_CONF_DIR}"
chmod -R 770 "${CADDY_RUN_DIR}"
chmod -R 770 "${CADDY_MAIN_LOG_DIR}"

# Format and overwrite the Caddyfile
(cd "${CADDY_DIR}" && /usr/local/bin/caddy fmt --overwrite)

# Write custom certs from the OPNsense Trust Store into a directory where Caddy can read them
/usr/local/opnsense/scripts/OPNsense/Caddy/caddy_certs.php
