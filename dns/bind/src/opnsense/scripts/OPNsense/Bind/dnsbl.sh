#!/bin/sh

DIR="/usr/local/etc/namedb/raw/"

if [ ! -d $DIR ]; then

  mkdir $DIR
  chown bind:bind $DIR
  chmod -R 755 $DIR

fi

cd $DIR

# Download all lists:

# EasyList:
fetch -q https://justdomains.github.io/blocklists/lists/easylist-justdomains.txt -o easylist-raw
sed "/\.$/d" easylist-raw > easylist
rm -f easylist-raw

# EasyPrivacy:
fetch -q https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt -o easyprivacy

# AdGuard:
fetch -q https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt -o adguard-raw
sed "/\.$/d" adguard-raw > adguard
rm -f adguard-raw

# NoCoin:
fetch -q https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt -o nocoin

# RansomWare Tracker abuse.ch:
fetch -q https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt -o rwtracker-comments
sed '/^#/ d' rwtracker-comments > rwtracker
rm -f rwtracker-comments

# MalwareDomains:
fetch -q http://malwaredomains.lehigh.edu/files/justdomains -o malwaredomains-comments
sed '/^#/ d' malwaredomains-comments > malwaredomains
rm -f malwaredomains-comments

# Put all files in correct format
FILES=`ls -1 /usr/local/etc/namedb/raw/`

for i in $FILES; do
  awk '{ print "zone " $1 " " $2 " {type master; file \"/usr/local/etc/namedb/master/blacklist.db\"; };" }' $i | sort | uniq > /usr/local/etc/namedb/$i.inc
done

# Depending on the options
RETVAL=0
case "$1" in
   "")
      echo "Usage: $0 ad|mw|all"
      RETVAL=1
      ;;
   ad)
      cd /usr/local/etc/namedb/ && cat easylist.inc easyprivacy.inc adguard.inc | sort | uniq > all.inc
      ;;
   mw)
      cd /usr/local/etc/namedb/ && cat nocoin.inc rwtracker.inc malwaredomains.inc | sort | uniq > all.inc
      ;;
   all)
      cd /usr/local/etc/namedb/ && cat easylist.inc easyprivacy.inc adguard.inc nocoin.inc rwtracker.inc malwaredomains.inc | sort | uniq > all.inc
      ;;
esac
exit $RETVAL
