#!/bin/sh

# This script is run
#  - when the plugin is installed (by +POST_INSTALL.post)
#  - when saving the "settings" form (which calls /api/crowdsec/service/reload)
#  - by hand, running "configctl crowdsec reconfigure"

set -e

# apply configuration options specific to opnsense
/usr/local/opnsense/scripts/OPNsense/CrowdSec/reconfigure.py

# enable pf anchor here - the tables and rules will be created by the bouncer
/usr/local/sbin/configctl filter reload >/dev/null

# the hub is upgraded by cron too
/usr/local/opnsense/scripts/OPNsense/CrowdSec/hub-upgrade.sh

# crowdsec was already restarted by hub-upgrade.sh
if service crowdsec_firewall enabled; then
    # have to check status explicitly because "restart" can set $? = 0 even when failing
    if ! service crowdsec_firewall status >/dev/null 2>&1; then
        service crowdsec_firewall start >/dev/null 2>&1 || :
    else
        service crowdsec_firewall restart >/dev/null 2>&1 || :
    fi
fi

# left from v0.0.8
rm -f /usr/local/etc/crowdsec/opnsense-settings.json

echo "OK"
