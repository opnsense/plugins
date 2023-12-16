#!/bin/sh

if [ -f /etc/rc.conf.d/haproxy ]; then
. /etc/rc.conf.d/haproxy
fi

# NOTE: Keep /var/haproxy on this list, see GH issue opnsense/plugins #39.
HAPROXY_DIRS="/var/haproxy /var/haproxy/sockets /var/haproxy/var/run /tmp/haproxy /tmp/haproxy/ssl /tmp/haproxy/lua /tmp/haproxy/errorfiles /tmp/haproxy/mapfiles /tmp/haproxy/sockets"

for directory in ${HAPROXY_DIRS}; do
    mkdir -p ${directory}
    chown -R www:www ${directory}
    chmod -R 750 ${directory}
done

# chroot dir must not be writable
find /var/haproxy -type d -exec chmod 550 {} \;

# export required data to filesystem
/usr/local/opnsense/scripts/OPNsense/HAProxy/exportCerts.php > /dev/null 2>&1
/usr/local/opnsense/scripts/OPNsense/HAProxy/exportLuaScripts.php > /dev/null 2>&1
/usr/local/opnsense/scripts/OPNsense/HAProxy/exportErrorFiles.php > /dev/null 2>&1
/usr/local/opnsense/scripts/OPNsense/HAProxy/exportMapFiles.php > /dev/null 2>&1

# deploy new config
case "$1" in
deploy)
    # run syntax check against newly generated config
    if /usr/local/sbin/haproxy -c -f /usr/local/etc/haproxy.conf.staging > /dev/null 2>&1; then
        cp /usr/local/etc/haproxy.conf.staging /usr/local/etc/haproxy.conf
    fi
    ;;
esac

exit 0
