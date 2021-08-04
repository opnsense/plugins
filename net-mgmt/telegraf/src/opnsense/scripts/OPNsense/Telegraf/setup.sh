#!/bin/sh

mkdir -p /usr/local/etc/telegraf.d /var/log/telegraf
chown -R telegraf:telegraf /usr/local/etc/telegraf.d /var/log/telegraf
chmod 750 /usr/local/etc/telegraf.d /var/log/telegraf

/usr/sbin/pw groupmod proxy -m telegraf
/usr/sbin/pw groupmod unbound -m telegraf
