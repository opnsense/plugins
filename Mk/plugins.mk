# Copyright (c) 2015-2017 Franco Fichtner <franco@opnsense.org>
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

all: check

LOCALBASE?=		/usr/local
PKG!=			which pkg || echo true

PLUGIN_DESC=		pkg-descr
PLUGIN_SCRIPTS=		+PRE_INSTALL +POST_INSTALL \
			+PRE_DEINSTALL +POST_DEINSTALL

PLUGINSDIR=		${.CURDIR}/../..
TEMPLATESDIR=		${PLUGINSDIR}/Templates

PLUGIN_WWW?=		https://opnsense.org/
PLUGIN_REVISION?=	0

PLUGIN_REQUIRES=	PLUGIN_NAME PLUGIN_VERSION PLUGIN_COMMENT \
			PLUGIN_MAINTAINER

check:
.for PLUGIN_REQUIRE in ${PLUGIN_REQUIRES}
.  if "${${PLUGIN_REQUIRE}}" == ""
.    error "${PLUGIN_REQUIRE} not set"
.  endif
.endfor

PLUGIN_DEVEL?=		yes

PLUGIN_PREFIX?=		os-
.if "${PLUGIN_DEVEL}" != ""
PLUGIN_SUFFIX?=		-devel
.endif

PLUGIN_PKGNAME=		${PLUGIN_PREFIX}${PLUGIN_NAME}${PLUGIN_SUFFIX}

.if "${PLUGIN_REVISION}" != "" && "${PLUGIN_REVISION}" != "0"
PLUGIN_PKGVERSION=	${PLUGIN_VERSION}_${PLUGIN_REVISION}
.else
PLUGIN_PKGVERSION=	${PLUGIN_VERSION}
.endif

name: check
	@echo ${PLUGIN_PKGNAME}

depends: check
	@echo ${PLUGIN_DEPENDS}

manifest: check
	@echo "name: ${PLUGIN_PKGNAME}"
	@echo "version: \"${PLUGIN_PKGVERSION}\""
	@echo "origin: opnsense/${PLUGIN_PKGNAME}"
	@echo "comment: \"${PLUGIN_COMMENT}\""
	@echo "maintainer: \"${PLUGIN_MAINTAINER}\""
	@echo "categories: [ \"${.CURDIR:S/\// /g:[-2]}\" ]"
	@echo "www: \"${PLUGIN_WWW}\""
	@echo "prefix: \"${LOCALBASE}\""
	@echo "licenselogic: \"single\""
	@echo "licenses: [ \"BSD2CLAUSE\" ]"
.if defined(PLUGIN_NO_ABI)
	@echo "arch: `pkg config abi | tr '[:upper:]' '[:lower:]' | cut -d: -f1`:*:*"
	@echo "abi: `pkg config abi | cut -d: -f1`:*:*"
.endif
.if defined(PLUGIN_DEPENDS)
	@echo "deps: {"
	@for PLUGIN_DEPEND in ${PLUGIN_DEPENDS}; do \
		if ! ${PKG} query '  %n: { version: "%v", origin: "%o" }' \
		    $${PLUGIN_DEPEND}; then \
			echo ">>> Missing dependency: $${PLUGIN_DEPEND}" >&2; \
			exit 1; \
		fi; \
	done
	@echo "}"
.endif

scripts: check scripts-pre scripts-auto scripts-manual scripts-post

scripts-pre:
	@for SCRIPT in ${PLUGIN_SCRIPTS}; do \
		rm -f ${DESTDIR}/$${SCRIPT}; \
		if [ -f ${.CURDIR}/$${SCRIPT}.pre ]; then \
			cp ${.CURDIR}/$${SCRIPT}.pre ${DESTDIR}/$${SCRIPT}; \
		fi; \
	done

scripts-auto:
	@if [ -d ${.CURDIR}/${SRC}/etc/rc.syshook.d ]; then \
		for SYSHOOK in early start; do \
			for FILE in $$(cd ${.CURDIR}/src/etc/rc.syshook.d && \
			    find -s . -type f -name "*.$${SYSHOOK}"); do \
				echo ${LOCALBASE}/etc/rc.syshook.d/$${FILE#./} >> \
				    ${DESTDIR}/+POST_INSTALL; \
			done; \
		done; \
	fi
	@if [ -d ${.CURDIR}/src/opnsense/service/conf/actions.d ]; then \
		cat ${TEMPLATESDIR}/actions.d >> ${DESTDIR}/+POST_INSTALL; \
	fi
	@if [ -d ${.CURDIR}/${SRC}/etc/rc.loader.d ]; then \
		for SCRIPT in +POST_INSTALL +POST_DEINSTALL; do \
			cat ${TEMPLATESDIR}/rc.loader.d >> \
			    ${DESTDIR}/$${SCRIPT}; \
		done; \
	fi
	@if [ -d ${.CURDIR}/src/opnsense/mvc/app/models ]; then \
		for FILE in $$(cd ${.CURDIR}/src/opnsense/mvc/app/models && \
		    find -s . -depth 2 -type d); do \
			cat ${TEMPLATESDIR}/models | \
			    sed "s:%%ARG%%:$${FILE#./}:g" >> \
			    ${DESTDIR}/+POST_INSTALL; \
		done; \
	fi
	@if [ -d ${.CURDIR}/src/opnsense/service/templates ]; then \
		for FILE in $$(cd ${.CURDIR}/src/opnsense/service/templates && \
		    find -s . -depth 2 -type d); do \
			cat ${TEMPLATESDIR}/templates | \
			    sed "s:%%ARG%%:$${FILE#./}:g" >> \
			    ${DESTDIR}/+POST_INSTALL; \
		done; \
	fi

scripts-manual:
	@for SCRIPT in ${PLUGIN_SCRIPTS}; do \
		if [ -f ${.CURDIR}/$${SCRIPT} ]; then \
			cp ${.CURDIR}/$${SCRIPT} ${DESTDIR}/$${SCRIPT}; \
		fi; \
	done

scripts-post:
	@for SCRIPT in ${PLUGIN_SCRIPTS}; do \
		if [ -f ${.CURDIR}/$${SCRIPT}.post ]; then \
			cat ${.CURDIR}/$${SCRIPT}.post >> ${DESTDIR}/$${SCRIPT}; \
		fi; \
	done

install: check
	@mkdir -p ${DESTDIR}${LOCALBASE}/opnsense/version
	@(cd ${.CURDIR}/src; find * -type f) | while read FILE; do \
		tar -C ${.CURDIR}/src -cpf - $${FILE} | \
		    tar -C ${DESTDIR}${LOCALBASE} -xpf -; \
	done
	@echo "${PLUGIN_PKGVERSION}" > "${DESTDIR}${LOCALBASE}/opnsense/version/${PLUGIN_NAME}"

plist: check
	@(cd ${.CURDIR}/${SRC}; find * -type f) | while read FILE; do \
		echo ${LOCALBASE}/$${FILE}; \
	done
	@echo "${LOCALBASE}/opnsense/version/${PLUGIN_NAME}"

description: check
	@if [ -f ${.CURDIR}/${PLUGIN_DESC} ]; then \
		cat ${.CURDIR}/${PLUGIN_DESC}; \
	fi

metadata: check
	@mkdir -p ${DESTDIR}
	@${MAKE} DESTDIR=${DESTDIR} scripts
	@${MAKE} DESTDIR=${DESTDIR} manifest > ${DESTDIR}/+MANIFEST
	@${MAKE} DESTDIR=${DESTDIR} description > ${DESTDIR}/+DESC
	@${MAKE} DESTDIR=${DESTDIR} plist > ${DESTDIR}/plist

collect: check
	@(cd ${.CURDIR}/${SRC}; find * -type f) | while read FILE; do \
		tar -C ${DESTDIR}${LOCALBASE} -cpf - $${FILE} | \
		    tar -C ${.CURDIR}/${SRC} -xpf -; \
	done

remove: check
	@(cd ${.CURDIR}/${SRC}; find * -type f) | while read FILE; do \
		rm -f ${DESTDIR}${LOCALBASE}/$${FILE}; \
	done
	@(cd ${.CURDIR}/${SRC}; find * -type d -depth) | while read DIR; do \
		if [ -d ${DESTDIR}${LOCALBASE}/$${DIR} ]; then \
			rmdir ${DESTDIR}${LOCALBASE}/$${DIR} 2> /dev/null || true; \
		fi; \
	done

WRKDIR?=${.CURDIR}/work
WRKSRC?=${WRKDIR}/${SRC}
PKGDIR?=${WRKDIR}/pkg

package: check
	@rm -rf ${WRKSRC}
	@mkdir -p ${WRKSRC} ${PKGDIR}
.for DEP in ${PLUGIN_DEPENDS}
	@if ! ${PKG} info ${DEP} > /dev/null; then ${PKG} install -yA ${DEP}; fi
.endfor
	@${MAKE} DESTDIR=${WRKSRC} FLAVOUR=${FLAVOUR} metadata
	@${MAKE} DESTDIR=${WRKSRC} FLAVOUR=${FLAVOUR} install
	@${PKG} create -v -m ${WRKSRC} -r ${WRKSRC} \
	    -p ${WRKSRC}/plist -o ${PKGDIR}

upgrade-check: check
	@if ! ${PKG} info ${PLUGIN_PKGNAME} > /dev/null; then \
		echo ">>> Cannot find package.  Please run 'pkg install ${PLUGIN_PKGNAME}'" >&2; \
		exit 1; \
	fi
	@rm -rf ${PKGDIR}

upgrade: upgrade-check package
	@${PKG} delete -fy ${PLUGIN_PKGNAME}
	@${PKG} add ${PKGDIR}/*.txz

mount: check
	mount_unionfs ${.CURDIR}/${SRC} ${DESTDIR}${LOCALBASE}

umount: check
	umount -f "<above>:${.CURDIR}/${SRC}"

clean: check
	@git reset -q . && git checkout -f . && git clean -xdqf .

lint: check
	find ${.CURDIR}/${SRC} \
	    -name "*.sh" -type f -print0 | xargs -0 -n1 sh -n
	find ${.CURDIR}/${SRC} \
	    -name "*.xml" -type f -print0 | xargs -0 -n1 xmllint --noout
	find ${.CURDIR}/${SRC} \
	    ! -name "*.xml" ! -name "*.xml.sample" ! -name "*.eot" \
	    ! -name "*.svg" ! -name "*.woff" ! -name "*.woff2" \
	    ! -name "*.otf" ! -name "*.png" ! -name "*.js" \
	    ! -name "*.scss" ! -name "*.py" ! -name "*.ttf" \
	    ! -name "*.tgz" ! -name "*.xml.dist" ! -name "*.sh" \
	    -type f -print0 | xargs -0 -n1 php -l

sweep: check
	find ${.CURDIR}/${SRC} -type f -name "*.map" -print0 | \
	    xargs -0 -n1 rm
	if grep -nr sourceMappingURL= ${.CURDIR}/${SRC}; then \
		echo "Mentions of sourceMappingURL must be removed"; \
		exit 1; \
	fi
	find ${.CURDIR}/${SRC} ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.ser" -type f -print0 | \
	    xargs -0 -n1 ${.CURDIR}/../../Scripts/cleanfile
	find ${.CURDIR} -type f -depth 1 -print0 | \
	    xargs -0 -n1 ${.CURDIR}/../../Scripts/cleanfile

style: check
	@: > ${.CURDIR}/.style.out
	@if [ -d ${.CURDIR}/${SRC} ]; then \
	    (phpcs --standard=${.CURDIR}/../../ruleset.xml \
	    ${.CURDIR}/${SRC} || true) > ${.CURDIR}/.style.out; \
	fi
	@echo -n "Total number of style warnings: "
	@grep '| WARNING' ${.CURDIR}/.style.out | wc -l
	@echo -n "Total number of style errors:   "
	@grep '| ERROR' ${.CURDIR}/.style.out | wc -l
	@cat ${.CURDIR}/.style.out
	@rm ${.CURDIR}/.style.out

style-fix: check
	@if [ -d ${.CURDIR}/${SRC} ]; then \
	    phpcbf --standard=${.CURDIR}/../../ruleset.xml \
	    ${.CURDIR}/${SRC} || true; \
	fi

.PHONY:	check
