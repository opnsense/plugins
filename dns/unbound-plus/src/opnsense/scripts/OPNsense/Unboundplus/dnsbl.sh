#!/bin/sh

# Copyright (c) 2018-2019 Michael Muenz <m.muenz@gmail.com>
# Copyright (c) 2018 Franco Fichtner <franco@opnsense.org>
# Copyright (c) 2019 Martin Wasley <martin@team-rebellion.net>
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

DESTDIR="/var/unbound/etc"
WORKDIRPREFIX="/tmp/unbounddnsbl."
WORKDIR="${WORKDIRPREFIX}${$}"

rm -rf ${WORKDIRPREFIX}*
mkdir -p ${WORKDIR}

download_list() {
	${FETCH} $1 -o ${WORKDIR}/raw.tmp
	
	if [ -s "/var/unbound/etc/whitelist.inc" ]; then
		WHITE=$(cat ${DESTDIR}/whitelist.inc | tr ',' '|')
	else 
		WHITE=""
	fi

	cat ${WORKDIR}/raw.tmp | grep '^127.0\|^0.0.0.0' | awk '{print $2}' | ( [ -z "$WHITE" ] && cat || egrep -v "$WHITE" ) >> ${WORKDIR}/domains.inc
	cat ${WORKDIR}/raw.tmp | grep '^[a-z]' | awk '{print $1}' | ( [ -z "$WHITE" ] && cat || egrep -v "$WHITE" ) >> ${WORKDIR}/domains.inc

	rm ${WORKDIR}/raw.tmp
}

install() {
		rm ${DESTDIR}/dnsbl.conf
		
		if [ -s "/var/unbound/etc/dnsbl.inc" ] || [ -s "/var/unbound/etc/lists.inc" ]; then
            # Join all files into one in Unbound format
			echo "server:" >> ${DESTDIR}/dnsbl.conf
			for DOMAIN in $(uniq -i ${WORKDIR}/domains.inc); do
				echo "local-data: \"${DOMAIN} A 0.0.0.0\"" >> ${DESTDIR}/dnsbl.conf
			done
			chown unbound:unbound ${DESTDIR}/dnsbl.conf
        fi

		rm -rf ${WORKDIR}
        pluginctl -s unbound restart
}

DNSBL=${1}

if [ -z "${DNSBL}" ]; then
	. /var/unbound/etc/dnsbl.inc
	DNSBL=${unbound_dnsbl}
fi

for CAT in $(echo ${DNSBL} | tr ',' ' '); do
	case "${CAT}" in
	aa)
		download_list https://adaway.org/hosts.txt
		;;
	ag)
		download_list https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt
		;;
	bla)
		download_list https://blocklist.site/app/dl/ads
		;;
	blf)
		download_list https://blocklist.site/app/dl/fraud
		;;
	blp)
		download_list https://blocklist.site/app/dl/phishing
		;;
	ca)
		download_list http://sysctl.org/cameleon/hosts
		;;
	el)
		download_list https://justdomains.github.io/blocklists/lists/easylist-justdomains.txt
		;;
	ep)
		download_list https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt
		;;
	emd)
		download_list https://hosts-file.net/emd.txt
		;;
	hpa)
		download_list https://hosts-file.net/ad_servers.txt
		;;
	hpf)
		download_list https://hosts-file.net/fsa.txt
		;;
	hpp)
		download_list https://hosts-file.net/psh.txt
		;;
	hup)
		download_list https://hosts-file.net/pup.txt
		;;
	nc)
		download_list https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt
		;;
	rw)
		download_list https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt	
		;;
	mw)
		download_list http://malwaredomains.lehigh.edu/files/justdomains
		;;
	pa)
		download_list https://raw.githubusercontent.com/chadmayfield/my-pihole-blocklists/master/lists/pi_blocklist_porn_all.list
		;;
	pt)
		download_list https://raw.githubusercontent.com/chadmayfield/pihole-blocklists/master/lists/pi_blocklist_porn_top1m.list
		;;
	sa)
		download_list https://s3.amazonaws.com/lists.disconnect.me/simple_ad.txt
		;;
	sb)
		download_list https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts
		;;
	st)
		download_list https://s3.amazonaws.com/lists.disconnect.me/simple_tracking.txt
		;;
	ws)
		download_list https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/spy.txt
		;;
	wsu)
		download_list https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/update.txt
		;;
	wse)
		download_list https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/extra.txt
		;;
	yy)
		download_list http://pgl.yoyo.org/adservers/serverlist.php?hostformat=hosts&mimetype=plaintext
		;;
	esac
done

# Download user defined blacklists
if [ -s "/var/unbound/etc/lists.inc" ]; then
	for URL in $(cat ${DESTDIR}/lists.inc | tr ',' ' '); do
		download_list ${URL}
	done
fi

install