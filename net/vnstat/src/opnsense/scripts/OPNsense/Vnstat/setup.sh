#!/bin/sh

mkdir -p /var/run/vnstat
chown -R vnstat:vnstat /var/run/vnstat
chmod 755 /var/run/vnstat

mkdir -p /var/lib/vnstat
chown -R vnstat:vnstat /var/lib/vnstat
chmod 755 /var/lib/vnstat /var/lib
