PLUGIN_NAME=		relayd
PLUGIN_VERSION=		2.0.d
PLUGIN_REVISION=	5
PLUGIN_DEPENDS=		relayd
PLUGIN_COMMENT=		Relayd Load Balancer
PLUGIN_MAINTAINER=	frank.brendel@eurolog.com

test:
	@cd /usr/local/opnsense/mvc/tests && \
	    phpunit --configuration PHPunit.xml \
	    ${.CURDIR}/src/opnsense/mvc/tests

.include "../../Mk/plugins.mk"
