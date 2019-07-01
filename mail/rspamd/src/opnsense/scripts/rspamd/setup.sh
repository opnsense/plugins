#!/bin/sh
mkdir -p /var/db/rspamd
mkdir -p /var/log/rspamd
mkdir -p /var/run/rspamd

# fix permissions of files generated by configd
chmod +r /usr/local/etc/rspamd/local.d/*
chmod +r /usr/local/etc/rspamd/maps.d/*
chmod o+rx /usr/local/etc/rspamd/local.d
chown -R rspamd /var/log/rspamd

chown -R rspamd:rspamd /var/db/rspamd
chown -R rspamd:rspamd /var/log/rspamd
chown -R rspamd:rspamd /var/run/rspamd
