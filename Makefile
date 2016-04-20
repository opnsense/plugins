PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

CATEGORIES=	devel net sysutils

.for CATEGORY in ${CATEGORIES}
_${CATEGORY}!=	ls -1d ${CATEGORY}/*
PLUGIN_DIRS+=	${_${CATEGORY}}
.endfor

list:
	@echo ${PLUGIN_DIRS}
