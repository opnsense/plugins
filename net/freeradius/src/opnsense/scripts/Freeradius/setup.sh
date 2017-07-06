#!/bin/sh

user=radiusd
group=radiusd

mkdir -p /var/run/radiusd
chown $user:$group /var/run/radiusd
chmod 750 /var/run/radiusd

mkdir -p /usr/local/etc/raddb/
chown $user:$group /usr/local/etc/raddb/
chmod 750 /usr/local/etc/raddb/

chown -R $user:$group /usr/local/etc/raddb
chown -R $user:$group /var/run/radiusd
