#!/bin/sh

# Copyright (C) 2026 Sergii Bogomolov
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
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

# Validates the configuration the plugin would generate. The [stage] action renders it under this
# root first, so validating never rewrites the file a restart would load.
#
# The verdict goes on the first line and the daemon's own words after it: configd hands back stdout
# alone, and a non-zero exit would discard even that.
set -u

STAGED=/var/cache/netflector/usr/local/etc/netflector.toml
DAEMON=/usr/local/bin/netflector

if [ ! -f "${STAGED}" ]; then
    echo failed
    echo "The plugin generated no configuration at all."
    exit 0
fi

# Nothing enabled is an off configuration, not a broken one: the template arms the daemon only when
# the service switch and at least one entry are on, so this state stops it rather than starting it.
# Handing the file over anyway would answer with the daemon's "define at least one reflector", which
# reads as a fault when nothing is wrong.
if ! grep -q '^\[reflectors\.' "${STAGED}"; then
    echo idle
    echo "Nothing to validate (no enabled reflectors, the service will not run)"
    exit 0
fi

if output=$("${DAEMON}" --check-config "${STAGED}" 2>&1); then
    echo ok
else
    echo failed
fi

# The staging path is an implementation detail.
printf '%s\n' "${output}" | sed "s|${STAGED}|the generated configuration|g"
