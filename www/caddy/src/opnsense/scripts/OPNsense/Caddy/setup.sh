#!/bin/sh

#
# Copyright (c) 2023-2025 Cedrik Pischem
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

# Detect configured caddy_user/group (defaults)
. /etc/rc.conf.d/caddy
CADDY_USER="${caddy_user:-root}"
CADDY_GROUP="${caddy_group:-wheel}"

# Define directories
CADDY_CONF_DIR="/usr/local/etc/caddy"
CADDY_DATA_DIR="/var/db/caddy"
CADDY_LOG_DIR="/var/log/caddy"
CADDY_CONF_CUSTOM_DIR="${CADDY_CONF_DIR}/caddy.d"
CADDY_CONF_CERT_DIR="${CADDY_CONF_DIR}/certificates"
CADDY_LOG_CUSTOM_DIR="${CADDY_LOG_DIR}/access"

# Group the main directories
CADDY_DIRS="
${CADDY_CONF_DIR}
${CADDY_DATA_DIR}
${CADDY_LOG_DIR}
${CADDY_CONF_CERT_DIR}
${CADDY_CONF_CUSTOM_DIR}
${CADDY_LOG_CUSTOM_DIR}
"

# No inode changes occur when directory already exists or permissions are correct.
# Always running these when caddy starts guarantees correct ownership with minimal read and writes.
mkdir -p ${CADDY_DIRS}
chown -R "${CADDY_USER}:${CADDY_GROUP}" ${CADDY_DIRS}

# Format and overwrite the Caddyfile
( cd "${CADDY_CONF_DIR}" && /usr/local/bin/caddy fmt --overwrite )

# Write custom certs from the OPNsense Trust Store
/usr/local/opnsense/scripts/OPNsense/Caddy/caddy_certs.php
