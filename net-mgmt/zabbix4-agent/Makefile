PLUGIN_SLAVE=		yes
MASTERDIR=		${.CURDIR}/../zabbix-agent
.include "${MASTERDIR}/Makefile"

PLUGIN_VARIANT=		4
PLUGIN_NAME=        zabbix${PLUGIN_VARIANT}-agent
PLUGIN_DEPENDS=		zabbix4-agent
PLUGIN_CONFLICTS=   zabbix-agent zabbix52-agent

.include "../../Mk/plugins.mk"
