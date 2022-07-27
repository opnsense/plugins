# Copyright (c) 2015-2020 Franco Fichtner <franco@opnsense.org>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

.include "Mk/defaults.mk"

CATEGORIES!=	ls -1d [a-z0-9]*
CATEGORIES:=	${CATEGORIES:Nruleset.xml}

.for CATEGORY in ${CATEGORIES}
_${CATEGORY}!=	ls -1d ${CATEGORY}/*
PLUGIN_DIRS+=	${_${CATEGORY}}
.endfor

list:
.for PLUGIN_DIR in ${PLUGIN_DIRS}
	@echo ${PLUGIN_DIR} -- $$(${MAKE} -C ${PLUGIN_DIR} -v PLUGIN_COMMENT) \
	    $$(if [ -n "$$(${MAKE} -C ${PLUGIN_DIR} -v PLUGIN_DEVEL _PLUGIN_DEVEL=)" ]; then echo "(development only)"; fi) \
	    $$(if [ -n "$$(${MAKE} -C ${PLUGIN_DIR} -v PLUGIN_OBSOLETE)" ]; then echo "(pending removal)"; fi)
.endfor

# shared targets that are sane to run from the root directory
TARGETS=	clean lint revision style style-fix style-python sweep test

.for TARGET in ${TARGETS}
${TARGET}:
.  for PLUGIN_DIR in ${PLUGIN_DIRS}
	@echo ">>> Entering ${PLUGIN_DIR}"
	@${MAKE} -C ${PLUGIN_DIR} ${TARGET}
.  endfor
.endfor

license:
	@${.CURDIR}/Scripts/license . > ${.CURDIR}/LICENSE

readme.md:
	@MAKE=${MAKE} Scripts/update-list.sh

sync: readme.md license

.PHONY: license readme.md sync
