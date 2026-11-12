#!/bin/sh

user=frr
group=frr

install -d -o $user -g $group -m 750 /var/run/frr
install -d -o $user -g $group -m 750 /usr/local/etc/frr
install -d -o $user -g $group -m 750 /var/log/frr

# ensure that frr can read the configuration files
chown -R $user:$group /usr/local/etc/frr
chown -R $user:$group /var/run/frr

# create missing logfiles without overwriting existing contents
touch /var/log/frr.log
chown $user:$group /var/log/frr.log
touch /var/log/frr/frr-reload.log
chown $user:$group /var/log/frr/frr-reload.log

# Reload templates here since we allow manual_config
/usr/local/sbin/pluginctl -c frr_sync

# register Security Associations
/usr/local/opnsense/scripts/frr/register_sas

# delete stale configuration files from frr.conf migration
files_to_delete="
    /etc/rc.d/watchfrr
    /usr/local/etc/frr/bfdd.conf
    /usr/local/etc/frr/bgpd.conf
    /usr/local/etc/frr/ospfd.conf
    /usr/local/etc/frr/ospf6d.conf
    /usr/local/etc/frr/ripd.conf
    /usr/local/etc/frr/staticd.conf
    /usr/local/etc/frr/zebra.conf
"

rm -f $files_to_delete
