#!/bin/sh
mkdir -p /var/db/tor
mkdir -p /var/log/tor
mkdir -p /var/run/tor

chown _tor:_tor /var/db/tor
chmod 700 /var/db/tor

# required to access the pf device for nat
/usr/sbin/pw groupmod proxy -m _tor
