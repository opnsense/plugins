PLUGIN_NAME?=		zabbix-agent
PLUGIN_VERSION=		1.8
PLUGIN_COMMENT=		Zabbix monitoring agent
PLUGIN_DEPENDS?=	zabbix5-agent
PLUGIN_CONFLICTS?=	zabbix4-agent zabbix52-agent
PLUGIN_MAINTAINER=	opnsense@moov.de

.if !defined(PLUGIN_SLAVE)
.include "../../Mk/plugins.mk"
.endif
