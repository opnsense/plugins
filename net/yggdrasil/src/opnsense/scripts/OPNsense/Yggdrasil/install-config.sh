#!/bin/sh

umask 077
exec /usr/bin/install -o root -g wheel -m 0600 \
    /usr/local/etc/yggdrasil/yggdrasil.conf \
    /usr/local/etc/yggdrasil.conf
