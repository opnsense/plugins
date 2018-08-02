#!/bin/sh

# NOTE: Keep /var/haproxy on this list, see GH issue opnsense/plugins #39.
HAPROXY_DIRS="/var/haproxy /var/haproxy/var/run /var/etc/haproxy/ssl /var/etc/haproxy/lua /var/etc/haproxy/errorfiles /var/etc/haproxy/mapfiles"

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

exit 0
