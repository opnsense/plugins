#!/bin/sh

# The watcher is meaningful only while named is running and a rendered mapping
# exists. Keeping this guard in the configd action makes GUI, CLI and boot
# service actions behave consistently.

WATCHER_CONFIG="/usr/local/etc/bind/dhcpwatcher.conf"
WATCHER="/usr/local/opnsense/scripts/OPNsense/Bind/dhcplease_watcher.py"

if ! /usr/local/etc/rc.d/named status >/dev/null 2>&1; then
    exit 0
fi

if [ ! -r "$WATCHER_CONFIG" ] || ! grep -q '^\[[0-9a-f-][0-9a-f-]*\]$' "$WATCHER_CONFIG"; then
    exit 0
fi

exec "$WATCHER"
