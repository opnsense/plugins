#!/bin/sh

mkdir -p /var/run/ntopng/
chmod 755 /var/run/ntopng
chown ntopng:ntopng /var/run/ntopng

mkdir -p /var/tmp/ntopng/
chmod 755 /var/tmp/ntopng
chown ntopng:wheel /var/tmp/ntopng

/usr/local/opnsense/scripts/OPNsense/Ntopng/generate_certs.php
