#!/bin/sh

user=quagga
group=quagga

mkdir -p /var/run/quagga
chown $user:$group /var/run/quagga
chmod 750 /var/run/quagga

mkdir -p /usr/local/etc/quagga
chown $user:$group /usr/local/etc/quagga
chmod 750 /usr/local/etc/quagga
