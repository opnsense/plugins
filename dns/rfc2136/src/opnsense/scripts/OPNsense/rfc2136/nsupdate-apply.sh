#!/bin/sh

# Copyright (c) 2024 Christian Blechert <christian@serverless.industries>
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

# Execute dyndns nsupdate triggered by interface events in rfc2136 plugin

set -e
set -u

# Arguments
ARG_SCRIPTFILE=""
ARG_KEYFILE=""
ARG_USETCP=""
ARG_CACHEFILE4=""
ARG_IP4=""
ARG_CACHEFILE6=""
ARG_IP6=""
ARG_HELP=0
UNKNOWN_OPTION=0

if [ $# -ge 1 ]
then
    while [ $# -ge 1 ]
    do
        key="$1"
        case $key in
            --scriptfile)
                shift
                ARG_SCRIPTFILE=$1
                ;;
            --keyfile)
                shift
                ARG_KEYFILE=$1
                ;;
            --tcp)
                ARG_USETCP="-v"
                ;;
            --cachefile4)
                shift
                ARG_CACHEFILE4=$1
                ;;
            --ip4)
                shift
                ARG_IP4=$1
                ;;
            --cachefile6)
                shift
                ARG_CACHEFILE6=$1
                ;;
            --ip6)
                shift
                ARG_IP6=$1
                ;;
            *)
                # unknown option
                UNKNOWN_OPTION=1
                ARG_HELP=1
                ;;
        esac
        shift # past argument or value
    done
else
    # no arguments passed, show help
    ARG_HELP=1
fi

# handle unknown options
if [ $UNKNOWN_OPTION -gt 0 ]
then
    >&2 echo "Unknown options."
fi

# check parameters
if [ -z "$ARG_KEYFILE" ] || [ -z "$ARG_SCRIPTFILE" ]
then
    ARG_HELP=1
fi

# show help and abort
if [ $ARG_HELP -gt 0 ]
then
    >&2 echo "Usage: $0 --scriptfile nsupdatecmds --keyfile keyfile [--tcp] [--cachefile4 cachefile4] [--ip4 x.x.x.x] [--cachefile6 cachefile6] [--ip6 xxxx::xxxx]"
    exit 1
fi

# execute nsupdate
now=$(date +%s)
/usr/local/bin/nsupdate -k $ARG_KEYFILE $ARG_USETCP $ARG_SCRIPTFILE
result=$?

# handle IPv4 cache file
if [ $result -eq 0 ] && [ -n "$ARG_CACHEFILE4" ] && [ -n "$ARG_IP4" ]
then
    >&2 echo "Create IPv4 cache file in '$ARG_CACHEFILE4'"
    echo -n "$ARG_IP4|$now" > $ARG_CACHEFILE4
elif [ -n "$ARG_CACHEFILE4" ]
then
    >&2 echo "Delete IPv4 cache file in '$ARG_CACHEFILE4'"
    rm -f $ARG_CACHEFILE4
fi

# handle IPv6 cache file
if [ $result -eq 0 ] && [ -n "$ARG_CACHEFILE6" ] && [ -n "$ARG_IP6" ]
then
    >&2 echo "Create IPv6 cache file in '$ARG_CACHEFILE6'"
    echo -n "$ARG_IP6|$now" > $ARG_CACHEFILE6
elif [ -n "$ARG_CACHEFILE6" ]
then
    >&2 echo "Delete IPv6 cache file in '$ARG_CACHEFILE6'"
    rm -f $ARG_CACHEFILE6
fi

# exit with nsupdate exit code
exit $result
