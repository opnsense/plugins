#!/bin/sh
# Sourced by rc.subr: a failed setup command alone only warns and continues.
/usr/local/opnsense/scripts/OPNsense/Nut/setup.sh || exit 1
if [ "${rc_arg}" = "start" ]; then
    /usr/local/opnsense/scripts/OPNsense/Nut/startup.py || exit 1
fi
