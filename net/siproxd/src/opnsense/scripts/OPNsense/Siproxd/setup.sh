#!/bin/sh

mkdir -p /var/run/siproxd
chown -R nobody:nogroup /var/run/siproxd
chmod 750 /var/run/siproxd

mkdir -p /var/lib/siproxd
chown -R nobody:nogroup /var/lib/siproxd
chmod 750 /var/lib/siproxd
