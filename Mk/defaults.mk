# Copyright (c) 2016-2024 Franco Fichtner <franco@opnsense.org>
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

OSABIPREFIX=	FreeBSD

LOCALBASE?=	/usr/local
PAGER?=		less

PKG=		${LOCALBASE}/sbin/pkg
.if ! exists(${PKG})
PKG=		true
.endif
GIT!=		which git || echo true

GITVERSION=	${SCRIPTSDIR}/version.sh

_PLUGIN_ARCH!=	uname -p
PLUGIN_ARCH?=	${_PLUGIN_ARCH}

VERSIONBIN=	${LOCALBASE}/sbin/opnsense-version

.if exists(${VERSIONBIN})
_PLUGIN_ABI!=	${VERSIONBIN} -a
PLUGIN_ABI?=	${_PLUGIN_ABI}
.else
PLUGIN_ABI?=	24.7
.endif

PLUGIN_MAINS=	master main
PLUGIN_MAIN?=	${PLUGIN_MAINS:[1]}
PLUGIN_STABLE?=	stable/${PLUGIN_ABI}

PHPBIN=		${LOCALBASE}/bin/php

.if exists(${PHPBIN})
_PLUGIN_PHP!=	${PHPBIN} -v
PLUGIN_PHP?=	${_PLUGIN_PHP:[2]:S/./ /g:[1..2]:tW:S/ //}
.endif

PYTHONLINK=	${LOCALBASE}/bin/python3

.if exists(${PYTHONLINK})
_PLUGIN_PYTHON!=${PYTHONLINK} -V
PLUGIN_PYTHON?=	${_PLUGIN_PYTHON:[2]:S/./ /g:[1..2]:tW:S/ //}
.endif


.for REPLACEMENT in ABI PHP PYTHON
. if empty(PLUGIN_${REPLACEMENT})
.  warning Cannot build without PLUGIN_${REPLACEMENT} set
. endif
.endfor

REPLACEMENTS=	PLUGIN_ABI \
		PLUGIN_ARCH \
		PLUGIN_HASH \
		PLUGIN_MAINTAINER \
		PLUGIN_NAME \
		PLUGIN_PKGNAME \
		PLUGIN_PKGVERSION \
		PLUGIN_TIER \
		PLUGIN_VARIANT \
		PLUGIN_WWW

SED_REPLACE=	# empty

.for REPLACEMENT in ${REPLACEMENTS}
SED_REPLACE+=	-e "s=%%${REPLACEMENT}%%=${${REPLACEMENT}}=g"
.endfor

ARGS=	diff feed mfc

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

ensure-stable:
	@if ! git show-ref --verify --quiet refs/heads/${PLUGIN_STABLE}; then \
		git update-ref refs/heads/${PLUGIN_STABLE} refs/remotes/origin/${PLUGIN_STABLE}; \
		git config branch.${PLUGIN_STABLE}.merge refs/heads/${PLUGIN_STABLE}; \
		git config branch.${PLUGIN_STABLE}.remote origin; \
	fi

diff_ARGS?= 	.

diff: ensure-stable
	@git diff --stat -p ${PLUGIN_STABLE} ${.CURDIR}/${diff_ARGS:[1]}

feed: ensure-stable
	@git log --stat -p --reverse ${PLUGIN_STABLE}...${feed_ARGS:[1]}~1

mfc_ARGS?=	.

mfc: ensure-stable
.for MFC in ${mfc_ARGS}
.if exists(${MFC})
	@git diff --stat -p ${PLUGIN_STABLE} ${.CURDIR}/${MFC} > /tmp/mfc.diff
	@git checkout ${PLUGIN_STABLE}
	@git apply /tmp/mfc.diff
	@git add ${.CURDIR}/${MFC}
	@if ! git diff --quiet HEAD; then \
		git commit -m "${MFC:S/^.$/${PLUGIN_DIR}/}: sync with ${PLUGIN_MAIN}"; \
	fi
.else
	@git checkout ${PLUGIN_STABLE}
	@if ! git cherry-pick -x ${MFC}; then \
		git cherry-pick --abort; \
	fi
.endif
	@git checkout ${PLUGIN_MAIN}
.endfor

stable:
	@git checkout ${PLUGIN_STABLE}

${PLUGIN_MAINS}:
	@git checkout ${PLUGIN_MAIN}

rebase:
	@git checkout ${PLUGIN_STABLE}
	@git rebase -i
	@git checkout ${PLUGIN_MAIN}

log:
	@git log --stat -p ${PLUGIN_STABLE}

push:
	@git checkout ${PLUGIN_STABLE}
	@git push
	@git checkout ${PLUGIN_MAIN}
