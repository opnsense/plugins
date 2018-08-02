#!/bin/sh

if [ -f /etc/rc.conf.d/haproxy ]; then
. /etc/rc.conf.d/haproxy
fi

rcprefix=

case "$1" in
stop)
    if [ "${haproxy_hardstop}" == "YES" ]; then
        rcprefix="hard"
    fi
    ;;
reload)
    if [ "${haproxy_softreload}" == "YES" ]; then
        rcprefix="soft"
    elif [ "${haproxy_hardstop}" == "YES" ]; then
        rcprefix="hard"
    fi
    ;;
esac

/usr/local/etc/rc.d/haproxy ${rcprefix}${1}
