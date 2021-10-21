#!/bin/sh

mkdir -p /var/db/chrony /var/lib/chrony /var/run/chrony
chown -R chronyd:chronyd /var/db/chrony /var/lib/chrony /var/run/chrony
chmod 750 /var/db/chrony /var/lib/chrony /var/run/chrony
