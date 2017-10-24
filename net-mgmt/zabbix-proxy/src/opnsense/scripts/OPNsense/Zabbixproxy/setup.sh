#!/bin/sh

# Setup database directory
mkdir -p /var/db/zabbix
chown -R zabbix:zabbix /var/db/zabbix
chmod 755 /var/db/zabbix

# Setup logging
touch /var/log/zabbix_proxy.log
chown -R zabbix:zabbix /var/log/zabbix_proxy.log
