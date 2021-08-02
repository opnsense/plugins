#!/bin/sh

mkdir -p /var/log/dnscrypt-proxy
chown -R _dnscrypt-proxy:_dnscrypt-proxy /var/log/dnscrypt-proxy
(cd /var/log && ln -s dnscrypt-proxy dnscryptproxy)
chown -R _dnscrypt-proxy:_dnscrypt-proxy /var/log/dnscryptproxy
