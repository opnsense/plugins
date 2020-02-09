#!/bin/sh

mkdir -p /var/munin/plugin-state/
chown -R munin:munin /var/munin
chmod 755 /var/cache/netdata
chmod 755 /var/db/netdata
chmod 755 /var/log/netdata
