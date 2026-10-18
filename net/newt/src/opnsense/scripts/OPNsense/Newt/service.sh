#!/bin/sh
# SPDX-License-Identifier: BSD-2-Clause
set -eu
rc=/usr/local/etc/rc.d/newt
case "${1:-}" in
    start) "$rc" start ;;
    stop)
        if "$rc" onestatus >/dev/null 2>&1; then "$rc" onestop; fi
        ;;
    restart)
        if "$rc" onestatus >/dev/null 2>&1; then "$rc" onestop; fi
        "$rc" start
        ;;
    status)
        if "$rc" onestatus >/dev/null 2>&1 &&
            [ -s /var/run/newt.child.pid ] &&
            kill -0 "$(cat /var/run/newt.child.pid)" 2>/dev/null; then
            "$rc" onestatus
        else
            echo 'newt is not running.'
        fi
        ;;
    *) echo 'Usage: service.sh {start|stop|restart|status}' >&2; exit 1 ;;
esac
