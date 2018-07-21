#!/bin/sh

# Copyright (c) 2018 Michael Muenz <m.muenz@gmail.com>
# Copyright (c) 2018 Franco Fichtner <franco@opnsense.org>
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

FETCH="/usr/bin/fetch -qT 5"

DESTDIR="/usr/local/etc/namedb"
WORKDIRPREFIX="/tmp/binddnsbl."
WORKDIR="${WORKDIRPREFIX}${$}"

rm -rf ${WORKDIRPREFIX}*
mkdir -p ${WORKDIR}

easylist() {
	# EasyList
	${FETCH} https://justdomains.github.io/blocklists/lists/easylist-justdomains.txt -o ${WORKDIR}/easylist-raw
	sed "/\.$/d" ${WORKDIR}/easylist-raw > ${WORKDIR}/easylist
	rm ${WORKDIR}/easylist-raw
}

easyprivacy() {
	# EasyPrivacy
	${FETCH} https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt -o ${WORKDIR}/easyprivacy-raw
	sed "/\.$/d" ${WORKDIR}/easyprivacy-raw > ${WORKDIR}/easyprivacy
	rm ${WORKDIR}/easyprivacy-raw
}

adguard() {
	# AdGuard
	${FETCH} https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt -o ${WORKDIR}/adguard-raw
	sed "/\.$/d" ${WORKDIR}/adguard-raw > ${WORKDIR}/adguard
	rm ${WORKDIR}/adguard-raw
}

nocoin() {
	# NoCoin
	${FETCH} https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt -o ${WORKDIR}/nocoin
}

rwtracker() {
	# RansomWare Tracker abuse.ch
	${FETCH} https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt -o ${WORKDIR}/rwtracker-comments
	sed '/^#/ d' ${WORKDIR}/rwtracker-comments > ${WORKDIR}/rwtracker
	rm ${WORKDIR}/rwtracker-comments
}

mwdomains() {
	# MalwareDomains
	${FETCH} http://malwaredomains.lehigh.edu/files/justdomains -o ${WORKDIR}/malwaredomains-comments
	sed '/^#/ d' ${WORKDIR}/malwaredomains-comments > ${WORKDIR}/malwaredomains
	rm ${WORKDIR}/malwaredomains-comments
}

install() {
	# Put all files in correct format
	for FILE in $(find ${WORKDIR} -type f); do
		awk '{ print "zone " $1 " " $2 " {type master; file \"/usr/local/etc/namedb/master/blacklist.db\"; };" }' ${FILE} | sort -u > ${FILE}.inc
	done
	# Merge resulting files (/dev/null in case there are none)
        cat $(find ${WORKDIR} -type f -name "*.inc") /dev/null | sort -u > ${DESTDIR}/dnsbl.inc
	chown bind:bind ${DESTDIR}/dnsbl.inc
        rm -rf ${WORKDIR}
}

for CAT in $(echo ${1} | tr ',' ' '); do
	case "${CAT}" in
	el)
		easylist
		;;
        ep)
		easyprivacy
		;;
        nc)
		nocoin
		;;
	rw)
		rwtracker
		;;
        mw)
		mwdomains
		;;
	esac
done

install
