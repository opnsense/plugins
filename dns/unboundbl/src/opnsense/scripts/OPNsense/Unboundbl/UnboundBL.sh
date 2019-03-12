#!/bin/sh
# unboundbl.sh, the main worker script for unboundbl
# maintained by alec armbruster (github.com/alectrocute)
# for opnsense project

# update() or -up pulls performs main functionality.
update() {
	# init counter for debugging purposes
	cnt=0
	empty_lines='(local-zone: "" redirect|local-data: " A 0.0.0.0")'
	echo "Starting DNSBL update!"
	echo "Cleaning up old files..."
	[ -f /var/unbound/dnsbl.conf ] && rm -f /var/unbound/dnsbl.conf
	[ -f /tmp/hosts.working ] && rm -f /tmp/hosts.working
	[ -f /tmp/hosts.working2 ] && rm -f /tmp/hosts.working2
	[ -f /tmp/hosts.domainlist.working ] && rm -f /tmp/hosts.domainlist.working
	[ -f /tmp/hosts.domainlist.working2 ] && rm -f /tmp/hosts.domainlist.working2
	# include config-generated blacklist/whitelist, commas replaced with spaces
	. /usr/local/opnsense/scripts/OPNsense/Unboundbl/data.sh
	printf "\n ------- Overview -------\n"
	echo " Whitelist entries:"
	echo " ${whitelist}"
	echo " Blocklist URLs to fetch:"
	echo " ${blacklist}"
	printf " ------------------------\n\n"
	# prep temp storage and conf file
	touch /tmp/hosts.working
	touch /tmp/hosts.working2
	touch /tmp/hosts.domainlist.working
	touch /tmp/hosts.domainlist.working2
	touch /var/unbound/dnsbl.conf.tmp
	echo "Generated temporary file for list generation."
	echo "Downloading external blocklists..."
	for url in $blacklist; do
	    echo "Attempting to download ${url} (via curl)..."
	    if curl --output /dev/null --silent --head --fail "${url}"; then
	        curl -s $url >> "/tmp/hosts.working"
	        cnt=$((cnt+1))
	        echo "  Downloaded successfully!"
	    else
 	        echo "  Error while trying to download..."
            fi
	done
	echo "Done downloading external blocklist URLs!"
	# sort all the lists and remove any whitelist items!
	echo "Parsing ${cnt} blocklist URLs..."
	# parse them out
	if [ -z "$whitelist" ]
	then
		# placeholder, impossible domain in case of empty whitelist
		# to stop forecoming process from erroring out.
		whitelist="(null.tld)"
	fi
	# catch any lines that aren't in the hosts-file format (eg. domain lists)
	awk -v whitelist="$whitelist" '$1 !~ /^127\.|^0\.|^::/ && $1 !~ /\;1/ && $1 !~ /\#/ && $2 !~ /[a-z]+\(\)/ && $1 !~ whitelist {gsub("\r",""); print tolower($1)}' /tmp/hosts.working | sort | uniq | \
	awk '{printf "local-zone: \"%s\" redirect\n", $1; printf "local-data: \"%s A 0.0.0.0\"\n", $1}' > /tmp/hosts.domainlist.working
	grep -F -v '$empty_line' /tmp/hosts.domainlist.working > /tmp/hosts.domainlist.working2
	# catch all lines in hosts-file format (eg. 127.0.0.1 domain.com)
	awk -v whitelist="$whitelist" '$1 ~ /^127\.|^0\.|^::/ && $1 !~ /\#/ && $1 !~ /\;1/ && $2 !~ whitelist {gsub("\r",""); print tolower($2)}' /tmp/hosts.working | sort | uniq | \
	awk '{printf "local-zone: \"%s\" redirect\n", $1; printf "local-data: \"%s A 0.0.0.0\"\n", $1}' > /tmp/hosts.working2
	# double check for whitelist removal
	grep -F -v "$whitelist" /tmp/hosts.working2 > /var/unbound/dnsbl.conf
	grep -F -v "$whitelist" /tmp/hosts.domainlist.working2 >> /var/unbound/dnsbl.conf
	# add "server:" line to top of file.
	echo "server:" | cat - /var/unbound/dnsbl.conf > /var/unbound/dnsbl.conf.tmp && mv /var/unbound/dnsbl.conf.tmp /var/unbound/dnsbl.conf
	echo "Done parsing blocklist URLs!"
	# math for stats
	domains=$(wc -l /var/unbound/dnsbl.conf | awk '{print $1;}')
	domains_total=$(echo $((domains / 2)))
	printf "\n --------- Stats --------\n"
	printf " Domains currently being blocked: $domains_total \n"
	printf " ------------------------\n\n"
	# clear the temp storage!
	echo "Cleaning up old temporary files..."
	[ -f /tmp/hosts.working ] && rm -f /tmp/hosts.working
	[ -f /tmp/hosts.working2 ] && rm -f /tmp/hosts.working2
	[ -f /tmp/hosts.domainlist.working ] && rm -f /tmp/hosts.domainlist.working
	[ -f /tmp/hosts.domainlist.working2 ] && rm -f /tmp/hosts.domainlist.working2
	echo "DNSBL update complete! Please restart your DNS resolver."
}

# to be expanded in the future, stats() or -stats displays
# the amount of domains on the included blocklist.
stats() {
	domains=$(wc -l /var/unbound/dnsbl.conf | awk '{print $1;}')
	domains_total=$(echo $((domains / 2)))
	printf "$domains_total"
}

# displays usage settings for manual usage, if desired
display_usage() {
	echo
	echo "Usage: UnboundBL.sh"
	echo
	echo " -h, --help   Display usage instructions"
	echo " -up, --update   Download and rebuild blocklist(s)."
	echo " -s, --stats   Display basic statistics of blocklist(s)."
	echo
}

# shell script functionality
argument="$1"
if [[ -z $argument ]] ; then
	raise_error "Can't run without an option. Here's some help:"
	display_usage
else
	case $argument in
		-h|--help)
		display_usage
		;;
	-up|--update)
		update
		;;
	-s|--stats)
		stats
		;;
	*)
		raise_error "Unknown argument: ${argument}"
		display_usage
		;;
	esac
fi
