#!/bin/sh
# unboundbl.sh, the main worker script for unboundbl
# maintained by alec armbruster (github.com/alectrocute)
# for opnsense project

# init counter for debugging purposes
cnt=0

echo "Starting DNSBL update!"
echo "Cleaning up old files..."
[ -f /var/unbound/dnsbl.conf ] && rm -f /var/unbound/dnsbl.conf
[ -f /tmp/hosts.working ] && rm -f /tmp/hosts.working
[ -f /tmp/hosts.working2 ] && rm -f /tmp/hosts.working2

# include config-generated blacklist/whitelist, commas replaced with spaces
. /usr/local/opnsense/scripts/OPNsense/Unboundbl/data.sh
printf "\n ------- Overview -------\n"
echo " Whitelist entries:"
echo " regex: ${whitelist}"
echo " Blocklist URLs to fetch:"
echo " ${blacklist}"
printf " ------------------------\n\n"

# prep temp storage and conf file
touch /tmp/hosts.working
touch /tmp/hosts.working2
touch /var/unbound/dnsbl.conf.tmp
echo "Generated temporary file for list generation."

echo "Downloading external blocklists..."
for url in $blacklist; do
    fetch -qo - $url >> "/tmp/hosts.working"
    cnt=$((cnt+1))
done
echo "Done downloading external blocklist URLs!"

# sort all the lists and remove any whitelist items!
echo "Parsing ${cnt} blocklist URLs..."
# parse them out
awk -v whitelist="$whitelist" '$1 ~ /^127\.|^0\./ && $2 !~ whitelist {gsub("\r",""); print tolower($2)}' /tmp/hosts.working | sort | uniq | \
awk '{printf "local-zone: \"%s\" redirect\n", $1; printf "local-data: \"%s A 0.0.0.0\"\n", $1}' > /tmp/hosts.working2
grep -F -v "$whitelist" /tmp/hosts.working2 > /var/unbound/dnsbl.conf
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

echo "DNSBL update complete! Please restart your DNS resolver."
