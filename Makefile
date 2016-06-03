PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

CATEGORIES=	devel net sysutils

.for CATEGORY in ${CATEGORIES}
_${CATEGORY}!=	ls -1d ${CATEGORY}/*
PLUGIN_DIRS+=	${_${CATEGORY}}
.endfor

list:
.for PLUGIN_DIR in ${PLUGIN_DIRS}
	@echo ${PLUGIN_DIR}
.endfor

lint:
.for PLUGIN_DIR in ${PLUGIN_DIRS}
	${MAKE} -C ${PLUGIN_DIR} lint
.endfor
