#!/bin/sh

user=frr
group=frr

mkdir -p /var/run/frr
chown $user:$group /var/run/frr
chmod 750 /var/run/frr

mkdir -p /usr/local/etc/frr
chown $user:$group /usr/local/etc/frr
chmod 750 /usr/local/etc/frr

# ensure that frr can read the configuration files
chown -R $user:$group /usr/local/etc/frr
chown -R $user:$group /var/run/frr

# logfile (if used)
touch /var/log/frr.log
chown $user:$group /var/log/frr.log

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
