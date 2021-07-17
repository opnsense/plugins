#!/bin/sh

mkdir -p /var/log/dnscrypt-proxy
chown -R _dnscrypt-proxy:_dnscrypt-proxy /var/log/dnscrypt-proxy
ln -s /var/log/dnscrypt-proxy /var/log/dnscryptproxy
