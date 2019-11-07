#!/bin/sh

mkdir -p /var/cache/netdata/
mkdir -p /var/db/netdata/
mkdir -p /var/log/netdata/
chown netdata:netdata /var/cache/netdata
chown netdata:netdata /var/db/netdata
chown netdata:netdata /var/log/netdata
chmod 750 /var/cache/netdata
chmod 750 /var/db/netdata
chmod 750 /var/log/netdata
