#!/bin/sh

# snmpd keeps its SNMPv3 engine ID and boot counter (oldEngineID and
# engineBoots) in its persistent file.  The configured users are rendered
# to a staging file instead and merged in here, so that the engine ID
# survives configuration changes and reboots (RFC 3411, RFC 3414 2.2).

PERSISTDIR=/var/net-snmp
PERSIST=${PERSISTDIR}/snmpd.conf
USERSDIR=/usr/local/etc/netsnmp
USERS=${USERSDIR}/snmpd_usercredentials.conf
PIDFILE=/var/run/net_snmpd.pid

mkdir -p ${PERSISTDIR}
chown -R root:wheel ${PERSISTDIR}
chmod 755 ${PERSISTDIR}

mkdir -p ${PERSISTDIR}/mib_indexes
chown -R root:wheel ${PERSISTDIR}/mib_indexes
chmod 700 ${PERSISTDIR}/mib_indexes

# staged credentials: templates inherit the directory mode on later renders
mkdir -p ${USERSDIR}
chown -R root:wheel ${USERSDIR}
chmod 700 ${USERSDIR}
if [ -f ${USERS} ]; then
	chmod 600 ${USERS}
fi

# On restart and reload rc.subr runs this while snmpd is still up.  snmpd
# rewrites its persistent file when it stops, so only merge when it is down.
if pgrep -qF ${PIDFILE} snmpd 2> /dev/null; then
	exit 0
fi

# Keep the engine state snmpd saved, replace the users with the configured
# ones.  Saved usmUser lines are dropped so removed users and changed
# credentials take effect.
TMP=$(mktemp ${PERSIST}.XXXXXX) || exit 1
{
	if [ -f ${PERSIST} ]; then
		grep -E '^(engineBoots|oldEngineID)[[:space:]]' ${PERSIST}
	fi
	if [ -f ${USERS} ]; then
		cat ${USERS}
	fi
} > ${TMP}
chmod 600 ${TMP}
mv -f ${TMP} ${PERSIST}
