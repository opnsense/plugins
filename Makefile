# Copyright (c) 2015-2019 Franco Fichtner <franco@opnsense.org>
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

PAGER?=		less

PLUGIN_ABI=	19.1

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

CATEGORIES=	benchmarks databases devel dns mail misc net-mgmt \
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
TARGETS=	clean lint style style-fix style-python sweep test

.for TARGET in ${TARGETS}
${TARGET}:
.  for PLUGIN_DIR in ${PLUGIN_DIRS}
	@${MAKE} -C ${PLUGIN_DIR} ${TARGET}
.  endfor
.endfor

ARGS=	diff mfc

# handle argument expansion for required targets
.for TARGET in ${.TARGETS}
_TARGET=		${TARGET:C/\-.*//}
.if ${_TARGET} != ${TARGET}
.for ARGUMENT in ${ARGS}
.if ${_TARGET} == ${ARGUMENT}
${_TARGET}_ARGS+=	${TARGET:C/^[^\-]*(\-|\$)//:S/,/ /g}
${TARGET}: ${_TARGET}
.endif
.endfor
${_TARGET}_ARG=		${${_TARGET}_ARGS:[0]}
.endif
.endfor

diff:
	@git diff --stat -p stable/${PLUGIN_ABI} ${.CURDIR}/${diff_ARGS:[1]}

mfc:
.for MFC in ${mfc_ARGS}
.if exists(${MFC})
	@git diff --stat -p stable/${PLUGIN_ABI} ${.CURDIR}/${MFC} > /tmp/mfc.diff
	@git checkout stable/${PLUGIN_ABI}
	@git apply /tmp/mfc.diff
	@git add ${.CURDIR}
	@if ! git diff --quiet HEAD; then \
		git commit -m "${MFC}: sync with master"; \
	fi
.else
	@git checkout stable/${PLUGIN_ABI}
	@git cherry-pick -x ${MFC}
.endif
	@git checkout master
.endfor

license:
	@${.CURDIR}/Scripts/license . > ${.CURDIR}/LICENSE

.PHONY: license
