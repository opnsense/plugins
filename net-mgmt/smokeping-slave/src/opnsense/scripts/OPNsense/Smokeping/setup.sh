#!/bin/sh

mkdir -p /var/log/smokeping
chown -R root:wheel /var/log/smokeping
chmod 750 /var/log/smokeping

mkdir -p /var/lib/smokeping
chown -R root:wheel /var/lib/smokeping
chmod 750 /var/lib/smokeping
