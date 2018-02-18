#!/bin/sh

if [ -f /etc/rc.conf.d/haproxy ]; then
. /etc/rc.conf.d/haproxy
fi

rcprefix=

case "$1" in
stop|restart)
    if [ "${haproxy_hardstop}" == "YES" ]; then
        rcprefix="hard"
    fi
    ;;
esac

/usr/local/etc/rc.d/haproxy ${rcprefix}${1}
