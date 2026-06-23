#!/bin/sh

RADSECPROXY_DIRS="/usr/local/etc/radsecproxy.d/certs"

for directory in ${RADSECPROXY_DIRS}; do
    mkdir -p ${directory}
    chown -R root:wheel ${directory}
    chmod -R 750 ${directory}
done


# export required certs to filesystem
/usr/local/opnsense/scripts/OPNsense/RadSecProxy/generate_certs.php > /dev/null 2>&1

exit 0
