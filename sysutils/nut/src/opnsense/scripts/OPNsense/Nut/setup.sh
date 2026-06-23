#!/bin/sh

pw groupmod -n dialer -m nut

mkdir -p /var/db/nut
chown -R nut:nut /var/db/nut
