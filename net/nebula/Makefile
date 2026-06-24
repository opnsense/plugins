PLUGIN_NAME=		nebula
PLUGIN_VERSION=		1.0
PLUGIN_COMMENT=		Nebula mesh overlay network
PLUGIN_DEPENDS=		nebula
PLUGIN_MAINTAINER=	henry@stern.ca

knobs:
	python3 tools/gen_knobs.py

knobs-check:
	python3 tools/gen_knobs.py --check

.include "../../Mk/plugins.mk"
