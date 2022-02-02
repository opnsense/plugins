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
