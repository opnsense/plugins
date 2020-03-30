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
restart)
    # The RC script always performs a "graceful" stop when using the
    # "restart" command. This behaviour cannot be altered. So we have to
    # manually perform a "hardstop" now.
    if [ "${haproxy_hardstop}" == "YES" ]; then
        /usr/local/etc/rc.d/haproxy hardstop
    fi
esac

/usr/local/etc/rc.d/haproxy ${rcprefix}${1}
