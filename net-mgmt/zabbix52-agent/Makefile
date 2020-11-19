PLUGIN_SLAVE=		yes
MASTERDIR=		${.CURDIR}/../zabbix-agent
.include "${MASTERDIR}/Makefile"

PLUGIN_VARIANT=		52
PLUGIN_NAME=        zabbix${PLUGIN_VARIANT}-agent
PLUGIN_DEPENDS=		zabbix52-agent
PLUGIN_CONFLICTS=   zabbix-agent zabbix4-agent

.include "../../Mk/plugins.mk"
