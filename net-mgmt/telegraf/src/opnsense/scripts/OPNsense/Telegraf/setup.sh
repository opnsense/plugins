#!/bin/sh

mkdir -p /var/log/telegraf
chown -R telegraf:telegraf /var/log/telegraf
chmod 750 /var/log/telegraf

/usr/sbin/pw groupmod proxy -m telegraf
