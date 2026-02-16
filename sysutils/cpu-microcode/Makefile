PLUGIN_NAME=		cpu-microcode-amd
PLUGIN_VERSION=		1.1
PLUGIN_COMMENT=		CPU microcode updates
PLUGIN_DEPENDS=		x86info
PLUGIN_MAINTAINER=	franco@opnsense.org
PLUGIN_VARIANTS=	amd intel
PLUGIN_TIER=		2

amd_NAME=		cpu-microcode-amd
amd_DEPENDS=		cpu-microcode-amd
amd_COMMENT:=		AMD ${PLUGIN_COMMENT}

intel_NAME=		cpu-microcode-intel
intel_DEPENDS=		cpu-microcode-intel
intel_COMMENT:=		Intel ${PLUGIN_COMMENT}

.include "../../Mk/plugins.mk"
