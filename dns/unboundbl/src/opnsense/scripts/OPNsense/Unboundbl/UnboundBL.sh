#!/bin/sh
# The main worker script for UnboundBL.

# init counter for debugging purposes
cnt=0

echo "Removing old files..."
[ -f /var/unbound/dnsbl.conf ] && rm -f /var/unbound/dnsbl.conf
[ -f /tmp/hosts.working ] && rm -f /tmp/hosts.working

# include config-generated blacklist/whitelist, commas replaced with spaces
. /usr/local/opnsense/scripts/OPNsense/Unboundbl/data.sh

# prep temp storage and conf file
echo "Generating temporary file for downloaded lists..."
touch /tmp/hosts.working

echo "Fetching blacklists..."
for url in $blacklist; do
    fetch -qo - $url >> "/tmp/hosts.working"
    cnt=$((cnt+1))
done

# sort all the lists and remove any whitelist items!
echo "Parsing ${cnt} blacklists..."
awk -v whitelist="$whitelist" '$1 ~ /^127\.|^0\./ && $2 !~ whitelist {gsub("\r",""); print tolower($2)}' /tmp/hosts.working | sort | uniq | \
awk '{printf "server:\n", $1; printf "local-data: \"%s A 0.0.0.0\"\n", $1}' > /var/unbound/dnsbl.conf

# clear the temp storage!
echo "Cleaning up..."
[ -f /tmp/hosts.working ] && rm -f /tmp/hosts.working
