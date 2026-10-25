#!/bin/sh

mkdir -p /var/run/turnserver
chown _turnserver:_turnserver /var/run/turnserver

/usr/local/opnsense/scripts/OPNsense/Turnserver/export_certs.php
