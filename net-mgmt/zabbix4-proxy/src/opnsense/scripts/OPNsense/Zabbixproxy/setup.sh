#!/bin/sh

# Setup database directory
mkdir -p /var/db/zabbix
chown -R zabbix:zabbix /var/db/zabbix
chmod 755 /var/db/zabbix

# Setup logging
touch /var/log/zabbix_proxy.log
chown -R zabbix:zabbix /var/log/zabbix_proxy.log

# Setup PID directory
mkdir -p /var/run/zabbix
chown -R zabbix:zabbix /var/run/zabbix
chmod 755 /var/run/zabbix
