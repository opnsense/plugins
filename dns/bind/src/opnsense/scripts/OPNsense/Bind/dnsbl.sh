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
	sed "/\.$/d" ${WORKDIR}/easylist-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/easylist
	rm ${WORKDIR}/easylist-raw
}

easyprivacy() {
	# EasyPrivacy
	${FETCH} https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt -o ${WORKDIR}/easyprivacy-raw
	sed "/\.$/d" ${WORKDIR}/easyprivacy-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/easyprivacy
	rm ${WORKDIR}/easyprivacy-raw
}

pornall() {
	# PornAll
	${FETCH} https://raw.githubusercontent.com/chadmayfield/my-pihole-blocklists/master/lists/pi_blocklist_porn_all.list -o ${WORKDIR}/pornall-raw
	sed "/\.$/d" ${WORKDIR}/pornall-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/pornall
	rm ${WORKDIR}/pornall-raw
}

porntop() {
	# PornTop1M
	${FETCH} https://raw.githubusercontent.com/chadmayfield/pihole-blocklists/master/lists/pi_blocklist_porn_top1m.list -o ${WORKDIR}/porntop-raw
	sed "/\.$/d" ${WORKDIR}/porntop-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/porntop
	rm ${WORKDIR}/porntop-raw
}

emdlist() {
	# EMD
	${FETCH} https://hosts-file.net/emd.txt -o ${WORKDIR}/emdlist-raw
	sed "/\.$/d" ${WORKDIR}/emdlist-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/emdlist
	rm ${WORKDIR}/emdlist-raw
}

adguard() {
	# AdGuard
	${FETCH} https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt -o ${WORKDIR}/adguard-raw
	sed "/\.$/d" ${WORKDIR}/adguard-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/adguard
	rm ${WORKDIR}/adguard-raw
}

nocoin() {
	# NoCoin
	${FETCH} https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt -o ${WORKDIR}/nocoin-raw
	sed "/\.$/d" ${WORKDIR}/nocoin-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/nocoin
	rm ${WORKDIR}/nocoin-raw
}

rwtracker() {
	# RansomWare Tracker abuse.ch
	${FETCH} https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt -o ${WORKDIR}/rwtracker-raw
	sed "/\.$/d" ${WORKDIR}/rwtracker-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/rwtracker
	rm ${WORKDIR}/rwtracker-raw
}

mwdomains() {
	# MalwareDomains
	${FETCH} http://malwaredomains.lehigh.edu/files/justdomains -o ${WORKDIR}/malwaredomains-raw
	sed "/\.$/d" ${WORKDIR}/malwaredomains-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/malwaredomains
	rm ${WORKDIR}/malwaredomains-raw
}

install() {
	# Put all files in correct format
	for FILE in $(find ${WORKDIR} -type f); do
		awk '{ if (length($1) < 245) print ""$1" CNAME .\n*."$1" CNAME ."}' ${FILE} | sort -u > ${FILE}.inc
	done
	# Merge resulting files (/dev/null in case there are none)
	cat $(find ${WORKDIR} -type f -name "*.inc") /dev/null | sort -u > ${DESTDIR}/dnsbl.inc
	chown bind:bind ${DESTDIR}/dnsbl.inc
	rm -rf ${WORKDIR}
}

for CAT in $(echo ${1} | tr ',' ' '); do
	case "${CAT}" in
	ag)
		adguard
		;;
	el)
		easylist
		;;
	ep)
		easyprivacy
		;;
	emd)
		emdlist
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
	pa)
		pornall
		;;
	pt)
		porntop
		;;
	esac
done

install
