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

DESTDIR="/usr/local/etc/dnscrypt-proxy"
WORKDIRPREFIX="/tmp/dnscryptproxydnsbl."
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

windowsspyblockerspy() {
	# WindowsSpyBlocker (spy)
	${FETCH} https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/spy.txt -o ${WORKDIR}/windowsspyblockerspy-raw
	sed "/\.$/d" ${WORKDIR}/windowsspyblockerspy-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/windowsspyblockerspy
	rm ${WORKDIR}/windowsspyblockerspy-raw
}

windowsspyblockerupdate() {
	# WindowsSpyBlocker (update)
	${FETCH} https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/update.txt -o ${WORKDIR}/windowsspyblockerupdate-raw
	sed "/\.$/d" ${WORKDIR}/windowsspyblockerupdate-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/windowsspyblockerupdate
	rm ${WORKDIR}/windowsspyblockerupdate-raw
}

windowsspyblockerextra() {
	# WindowsSpyBlocker (extra)
	${FETCH} https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/extra.txt -o ${WORKDIR}/windowsspyblockerextra-raw
	sed "/\.$/d" ${WORKDIR}/windowsspyblockerextra-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/windowsspyblockerextra
	rm ${WORKDIR}/windowsspyblockerextra-raw
}

adaway() {
	# AdAway List
	${FETCH} https://adaway.org/hosts.txt -o ${WORKDIR}/adaway-raw
	sed "/\.$/d" ${WORKDIR}/adaway-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/adaway
	rm ${WORKDIR}/adaway-raw
}

yoyo() {
	# YoYo List
	${FETCH} "http://pgl.yoyo.org/adservers/serverlist.php?hostformat=hosts&mimetype=plaintext" -o ${WORKDIR}/yoyo-raw
	sed "/\.$/d" ${WORKDIR}/yoyo-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/yoyo
	rm ${WORKDIR}/yoyo-raw
}

stevenblack() {
        # StevenBlack
        ${FETCH} https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts -o ${WORKDIR}/stevenblack-raw
        sed "/\.$/d" ${WORKDIR}/stevenblack-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | sed "/127\.0\.0\.1/d" | sed "/255\.255\.255\.255/d" | sed "/\:\:1/d" | sed "/fe80\:\:1/d" | sed "/ff00\:\:/d" | sed "/ff02\:\:/d" | sed "/0\.0\.0\.0 0\.0\.0\.0/d" | tr -d '\r' | awk 'BEGIN{FS=OFS=" ";}{print $2;}' > ${WORKDIR}/stevenblack
        rm ${WORKDIR}/stevenblack-raw
}

blocklistads() {
        # Blocklist.site Ads
        ${FETCH} https://blocklistproject.github.io/Lists/ads.txt -o ${WORKDIR}/blocklistads-raw
        sed "/\.$/d" ${WORKDIR}/blocklistads-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | sed "/127\.0\.0\.1/d" | sed "/255\.255\.255\.255/d" | sed "/\:\:1/d" | sed "/fe80\:\:1/d" | sed "/ff00\:\:/d" | sed "/ff02\:\:/d" | sed "/0\.0\.0\.0 0\.0\.0\.0/d" | awk '{print $2}' > ${WORKDIR}/blocklistads
        rm ${WORKDIR}/blocklistads-raw
}

blocklistfraud() {
        # Blocklist.site Fraud
        ${FETCH} https://blocklistproject.github.io/Lists/fraud.txt -o ${WORKDIR}/blocklistfraud-raw
        sed "/\.$/d" ${WORKDIR}/blocklistfraud-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | sed "/127\.0\.0\.1/d" | sed "/255\.255\.255\.255/d" | sed "/\:\:1/d" | sed "/fe80\:\:1/d" | sed "/ff00\:\:/d" | sed "/ff02\:\:/d" | sed "/0\.0\.0\.0 0\.0\.0\.0/d" |awk '{print $2}' > ${WORKDIR}/blocklistfraud
        rm ${WORKDIR}/blocklistfraud-raw
}

blocklistphishing() {
        # Blocklist.site Phishing
        ${FETCH} https://blocklistproject.github.io/Lists/phishing.txt -o ${WORKDIR}/blocklistphishing-raw
        sed "/\.$/d" ${WORKDIR}/blocklistphishing-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" | sed "/localhost/d" | sed "/127\.0\.0\.1/d" | sed "/255\.255\.255\.255/d" | sed "/\:\:1/d" | sed "/fe80\:\:1/d" | sed "/ff00\:\:/d" | sed "/ff02\:\:/d" | sed "/0\.0\.0\.0 0\.0\.0\.0/d" | awk '{print $2}' > ${WORKDIR}/blocklistphishing
        rm ${WORKDIR}/blocklistphishing-raw
}

simplead() {
	# Simple Ad List
	${FETCH} https://s3.amazonaws.com/lists.disconnect.me/simple_ad.txt -o ${WORKDIR}/simplead-raw
	sed "/\.$/d" ${WORKDIR}/simplead-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/simplead
	rm ${WORKDIR}/simplead-raw
}

simpletrack() {
	# Simple Tracking List
	${FETCH} https://s3.amazonaws.com/lists.disconnect.me/simple_tracking.txt -o ${WORKDIR}/simpletrack-raw
	sed "/\.$/d" ${WORKDIR}/simpletrack-raw | sed "/^#/d" | sed "/\_/d" | sed "/^\s*$/d" | sed "/\.\./d" | sed "s/^\.//g" > ${WORKDIR}/simpletrack
	rm ${WORKDIR}/simpletrack-raw
}

install() {
	# Put all files in correct format
	for FILE in $(find ${WORKDIR} -type f); do
		awk '{ if (length($1) < 245) print $1 }' ${FILE} | sort -u > ${FILE}.inc
	done
	# Merge resulting files (/dev/null in case there are none)
	cat $(find ${WORKDIR} -type f -name "*.inc") /dev/null | sort -u > ${DESTDIR}/blacklist.txt
	chown _dnscrypt-proxy:_dnscrypt-proxy ${DESTDIR}/blacklist.txt
	rm -rf ${WORKDIR}
}

DNSBL=${1}

if [ -z "${DNSBL}" ]; then
	. /etc/rc.conf.d/dnscrypt_proxy
	DNSBL=${dnscrypt_proxy_dnsbl}
fi

for CAT in $(echo ${DNSBL} | tr ',' ' '); do
	case "${CAT}" in
	aa)
		adaway
		;;
	ag)
		adguard
		;;
	bla)
		blocklistads
		;;
	blf)
		blocklistfraud
		;;
	blp)
		blocklistphishing
		;;
	el)
		easylist
		;;
	ep)
		easyprivacy
		;;
	nc)
		nocoin
		;;
	pt)
		porntop
		;;
	sa)
		simplead
		;;
	sb)
		stevenblack
		;;
	st)
		simpletrack
		;;
	ws)
		windowsspyblockerspy
		;;
	wsu)
		windowsspyblockerupdate
		;;
	wse)
		windowsspyblockerextra
		;;
	yy)
		yoyo
		;;
	esac
done

install
