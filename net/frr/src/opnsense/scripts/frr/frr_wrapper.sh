#!/bin/sh

# Copyright (c) 2025 Andy Binder <AndyBinder@gmx.de>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

# Service wrapper for starting/restarting frr service
# This wrapper is needed to react on specific service interactions through watchfrr.
# Startup details with watchfrr enabled (default):
# 1. "service frr start" calls "service frr start watchfrr"
# 2. watchfrr once started calls "service frr restart all"
# 3. "restart all" need to loop the list of $frr_daemons to start each
#    of then
# 4. vtysh -b is executed to load boot startup configuration

ACTION="$1"
COMMAND="$2"

/usr/sbin/service frr "$ACTION" "$COMMAND"
SERVICE_EXIT_CODE=$?

# If frr starts/restarts ospfd, e.g. on process error (parameter: start/restart ospfd)
if [ "$2" = "ospfd" ]; then
    logger -t frr_wrapper "WATCHFRR - OSPFD - Starting CARP event handler now"
    /usr/local/opnsense/scripts/frr/carp_event_handler
fi
# If frr starts up (parameter: restart all)
if [ "$2" = "all" ]; then
    (
        /usr/bin/logger -t frr_wrapper "WATCHFRR - STARTUP - Starting CARP event handler now"
        /usr/local/opnsense/scripts/frr/carp_event_handler
    ) &
fi
exit $SERVICE_EXIT_CODE
