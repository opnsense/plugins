#!/bin/sh

mkdir -p /var/run/clamav
chown -R clamav:clamav /var/run/clamav
chmod 750 /var/run/clamav

mkdir -p /var/db/clamav
chown -R clamav:clamav /var/db/clamav
chmod 750 /var/db/clamav

mkdir -p /var/log/clamav
chown -R clamav:clamav /var/log/clamav
chmod 750 /var/log/clamav
