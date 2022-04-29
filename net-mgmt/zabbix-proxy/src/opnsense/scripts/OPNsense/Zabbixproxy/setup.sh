#!/bin/sh

# Setup database directory
mkdir -p /var/db/zabbix
chown -R zabbix:zabbix /var/db/zabbix
chmod 755 /var/db/zabbix

# Setup logging
mkdir /var/log/zabbix
chown -R zabbix:zabbix /var/log/zabbix
chmod 770 /var/log/zabbix

# Setup PID directory
mkdir -p /var/run/zabbix
chown -R zabbix:zabbix /var/run/zabbix
chmod 755 /var/run/zabbix

# Setup SNMP Trap file
wget https://git.zabbix.com/projects/ZBX/repos/zabbix/raw/misc/snmptrap/zabbix_trap_receiver.pl -O /usr/bin/zabbix_trap_receiver.pl
chown -R zabbix:zabbix /tmp/zabbix_traps.tmp
