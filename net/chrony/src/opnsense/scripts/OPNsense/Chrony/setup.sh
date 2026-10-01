#!/bin/sh

mkdir -p /var/db/chrony /var/lib/chrony /var/run/chrony \
  /usr/local/etc/chrony/conf.d /var/run/chrony/conf.d \
  /usr/local/etc/chrony/sources.d /var/run/chrony/sources.d

chown -R chronyd:chronyd /var/db/chrony /var/lib/chrony /var/run/chrony \
  /usr/local/etc/chrony/conf.d /var/run/chrony/conf.d \
  /usr/local/etc/chrony/sources.d /var/run/chrony/sources.d

chmod 750 /var/db/chrony /var/lib/chrony /var/run/chrony \
  /usr/local/etc/chrony/conf.d /var/run/chrony/conf.d \
  /usr/local/etc/chrony/sources.d /var/run/chrony/sources.d
