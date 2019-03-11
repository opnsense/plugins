#!/bin/sh
# The main worker script for UnboundBL.

# include config-generated blacklist/whitelist, commas replaced with spaces
. /usr/local/opnsense/scripts/OPNsense/Unboundbl/data.sh

# prep temp storage and conf file
touch /tmp/hosts.working
rm /var/unbound/UnboundBL.conf

for url in $blacklist; do
    fetch -qo - $url >> "/tmp/hosts.working"
done

# sort all the lists and remove any whitelist items!
awk -v whitelist="$whitelist" '$1 ~ /^127\.|^0\./ && $2 !~ whitelist {gsub("\r",""); print tolower($2)}' /tmp/hosts.working | sort | uniq | \
awk '{printf "server:\n", $1; printf "local-data: \"%s A 0.0.0.0\"\n", $1}' > /var/unbound/UnboundBL.conf

# clear the temp storage!
rm /tmp/hosts.working
