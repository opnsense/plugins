PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

CATEGORIES=	benchmarks databases devel dns mail net-mgmt \
		net security sysutils www

.for CATEGORY in ${CATEGORIES}
_${CATEGORY}!=	ls -1d ${CATEGORY}/*
PLUGIN_DIRS+=	${_${CATEGORY}}
.endfor

list:
.for PLUGIN_DIR in ${PLUGIN_DIRS}
	@echo ${PLUGIN_DIR} -- $$(${MAKE} -C ${PLUGIN_DIR} -V PLUGIN_COMMENT)
.endfor

# shared targets that are sane to run from the root directory
TARGETS=	lint sweep style style-fix clean

.for TARGET in ${TARGETS}
${TARGET}:
.  for PLUGIN_DIR in ${PLUGIN_DIRS}
	@${MAKE} -C ${PLUGIN_DIR} ${TARGET}
.  endfor
.endfor

license:
	@${.CURDIR}/Scripts/license . > ${.CURDIR}/LICENSE

.PHONY: license
