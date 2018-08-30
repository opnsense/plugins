# !/bin/sh
# UnboundBL, UnboundBL.sh

# include config-generated blacklist/whitelist, commas replaced with spaces
source /usr/local/opnsense/scripts/OPNsense/UnboundBL/data.sh

# prep temp storage and conf file
touch /tmp/hosts.working

# curl all the lists!
for url in $blacklist; do
    curl --silent $url >> "/tmp/hosts.working"
done

# sort all the lists and remove any whitelist items!
awk -v whitelist="$whitelist" '$1 ~ /^127\.|^0\./ && $2 !~ whitelist {gsub("\r",""); print tolower($2)}' /tmp/hosts.working | sort | uniq | \
awk '{printf "server:\n", $1; printf "local-data: \"%s A 0.0.0.0\"\n", $1}' > /var/unbound/UnboundBL.conf

# clear the temp storage!
rm /tmp/hosts.working
