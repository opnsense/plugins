#!/bin/sh

AGENT_DIRS="/var/run/zabbix /var/log/zabbix /usr/local/etc/zabbix_agentd.conf.d"

for directory in ${AGENT_DIRS}; do
    mkdir -p ${directory}
    chown -R zabbix:zabbix ${directory}
    chmod -R 770 ${directory}
done

exit 0
