#!/bin/sh

# do not pass DNS information that clobbers /etc/resolv.conf
export INTERNAL_IP4_DNS=

. /usr/local/sbin/vpnc-script

# XXX we can register the proper DNS via ifctl(8) if required later
