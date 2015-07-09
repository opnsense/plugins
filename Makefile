PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

CATEGORIES=	devel

.for CATEGORY in ${CATEGORIES}
_PLUGIN_DIRS!=	ls -1d ${CATEGORY}/*
PLUGIN_DIRS+=	${_PLUGIN_DIRS}
.endfor

list:
	@echo ${PLUGIN_DIRS}
