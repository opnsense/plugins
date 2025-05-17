#!/bin/sh
# Service wrapper for restarting frr service
# This wrapper is needed to react on specific service interactions through watchfrr.
# Startup details with watchfrr enabled (default):
# 1. "service frr start" calls "service frr start watchfrr"
# 2. watchfrr once started calls "service frr restart all"
# 3. "restart all" need to loop the list of $frr_daemons to start each
#    of then
# 4. vtysh -b is executed to load boot startup configuration

/usr/sbin/service frr restart $1

# If started service is ospfd, e.g. process error
if [ "$1" = "ospfd" ]; then
    logger -t frr_wrapper "WATCHFRR - OSPFD - Starting CARP event handler now"
    /usr/local/opnsense/scripts/frr/carp_event_handler
fi
# If frr starts up
if [ "$1" = "all" ]; then
    /usr/bin/logger -t frr_wrapper "WATCHFRR - STARTUP - Starting CARP event handler in 1 sec."
    (
        sleep 1
        /usr/bin/logger -t frr_wrapper "WATCHFRR - STARTUP - Starting CARP event handler now"
        /usr/local/opnsense/scripts/frr/carp_event_handler
    ) &
fi
exit $?
