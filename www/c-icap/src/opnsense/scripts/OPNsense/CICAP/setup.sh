#!/bin/sh

mkdir -p /var/run/c-icap
chown -R c_icap:c_icap /var/run/c-icap
chmod 750 /var/run/c-icap

mkdir -p /var/log/c-icap
chown -R c_icap:c_icap /var/log/c-icap
chmod 750 /var/log/c-icap

mkdir -p /tmp/c-icap/templates/virus_scan/en
chmod -R 755 /tmp/c-icap/
