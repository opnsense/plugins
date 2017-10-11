#!/bin/sh
mkdir -p /var/db/tor
mkdir -p /var/log/tor
mkdir -p /var/run/tor

chown _tor:_tor /var/db/tor
chmod 700 /var/db/tor

touch /var/log/tor.log
chmod 700 /var/log/tor.log
chown _tor:_tor /var/log/tor.log

chown _tor:_tor /var/run/tor

# create Onion service dirs:
/usr/local/opnsense/service/scripts/tor/make_hidden_service_dirs.php

# required to access the pf device for nat
/usr/sbin/pw groupmod proxy -m _tor
