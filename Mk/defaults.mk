# Copyright (c) 2016-2025 Franco Fichtner <franco@opnsense.org>
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

SCRIPTSDIR=	${PLUGINSDIR}/Scripts
TEMPLATESDIR=	${PLUGINSDIR}/Templates

GITVERSION=	${SCRIPTSDIR}/version.sh

_PLUGIN_ARCH!=	uname -p
PLUGIN_ARCH?=	${_PLUGIN_ARCH}

VERSIONBIN=	${LOCALBASE}/sbin/opnsense-version

.if exists(${VERSIONBIN})
_PLUGIN_ABI!=	${VERSIONBIN} -a
PLUGIN_ABIS?=	${_PLUGIN_ABI}
.else
PLUGIN_ABIS?=	26.1
.endif

PLUGIN_ABI?=	${PLUGIN_ABIS:[1]}

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
		PLUGIN_CONFLICTS \
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

WRKDIR?=	${.CURDIR}/work
MFCDIR?=	/tmp/mfc.dir
PKGDIR?=	${WRKDIR}/pkg
WRKSRC?=	${WRKDIR}/src
TESTDIR?=	${.CURDIR}/src/opnsense/mvc/tests
