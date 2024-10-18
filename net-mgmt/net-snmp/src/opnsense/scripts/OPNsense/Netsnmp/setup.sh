#!/bin/sh

mkdir -p /var/net-snmp
chown -R root:wheel /var/net-snmp
chmod 755 /var/net-snmp

mkdir -p /var/net-snmp/mib_indexes
chown -R root:wheel /var/net-snmp/mib_indexes
chmod 700 /var/net-snmp/mib_indexes
