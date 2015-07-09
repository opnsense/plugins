PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

PLUGIN_DIRS!=	ls -1d [a-z]*

list:
	@echo ${PLUGIN_DIRS}
