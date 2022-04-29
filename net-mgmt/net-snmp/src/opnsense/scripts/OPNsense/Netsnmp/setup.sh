#!/bin/sh

mkdir -p /var/net-snmp
chown -R root:wheel /var/net-snmp
chmod 755 /var/net-snmp

mkdir -p /var/net-snmp/mib_indexes
chown -R root:wheel /var/mib_indexes
chmod 700 /var/net-snmp/mib_indexes

mkdir /usr/local/etc/snmp
echo 'authCommunity log,execute,net public' >> /usr/local/etc/snmp/snmptrapd.conf
echo 'disableAuthorization yes' >> /usr/local/etc/snmp/snmptrapd.conf
echo 'perl do "/usr/bin/zabbix_trap_receiver.pl";' >> /usr/local/etc/snmp/snmptrapd.conf
wget https://git.zabbix.com/projects/ZBX/repos/zabbix/raw/misc/snmptrap/zabbix_trap_receiver.pl -O /usr/bin/zabbix_trap_receiver.pl
