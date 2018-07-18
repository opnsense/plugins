#!/bin/sh

# Copyright (c) 2018 Michael Muenz <m.muenz@gmail.com>
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

SORT=/usr/bin/sort
UNIQ=/usr/bin/uniq
CD=/usr/bin/cd
CAT=/bin/cat
AWK=/usr/bin/awk
FETCH="/usr/bin/fetch -qT 5"
RM="/bin/rm -f"

RAWDIR="/usr/local/etc/namedb/raw/"
WORKDIR="/usr/local/etc/namedb/"

/bin/mkdir -p $RAWDIR
/usr/sbin/chown bind:bind $RAWDIR
/bin/chmod -R 755 $RAWDIR


# Download all lists:

# EasyList:
easylist() {
$CD $RAWDIR
${FETCH} https://justdomains.github.io/blocklists/lists/easylist-justdomains.txt -o easylist-raw
sed "/\.$/d" easylist-raw > easylist
${RM} easylist-raw
}

# EasyPrivacy:
easyprivacy() {
$CD $RAWDIR
${FETCH} https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt -o easyprivacy-raw
sed "/\.$/d" easyprivacy-raw > easyprivacy
${RM} easyprivacy-raw
}

# AdGuard:
adguard() {
$CD $RAWDIR
${FETCH} https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt -o adguard-raw
sed "/\.$/d" adguard-raw > adguard
${RM} adguard-raw
}

# NoCoin:
nocoin() {
$CD $RAWDIR
${FETCH} https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt -o nocoin
}

# RansomWare Tracker abuse.ch:
rwtracker() {
$CD $RAWDIR
${FETCH} https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt -o rwtracker-comments
sed '/^#/ d' rwtracker-comments > rwtracker
${RM} rwtracker-comments
}

# MalwareDomains:
mwdomains() {
$CD $RAWDIR
${FETCH} http://malwaredomains.lehigh.edu/files/justdomains -o malwaredomains-comments
sed '/^#/ d' malwaredomains-comments > malwaredomains
${RM} malwaredomains-comments
}

# Put all files in correct format
convert() {
$CD $RAWDIR
FILES=`ls -1`

for i in $FILES; do
  $AWK '{ print "zone " $1 " " $2 " {type master; file \"/usr/local/etc/namedb/master/blacklist.db\"; };" }' $i | $SORT | $UNIQ > $WORKDIR/$i.inc
done
}

# Depending on the options
RETVAL=0
case "$1" in
   "")
      echo "Usage: $0 ad|mw|all"
      RETVAL=1
      ;;
   ad)
      easylist
      easyprivacy
      nocoin
      convert
      $CAT $WORKDIR/easylist.inc $WORKDIR/easyprivacy.inc $WORKDIR/adguard.inc | $SORT | $UNIQ > $WORKDIR/all.inc
      ;;
   mw)
      rwtracker
      mwdomains
      convert
      $CAT $WORKDIR/nocoin.inc $WORKDIR/rwtracker.inc $WORKDIR/malwaredomains.inc | $SORT | $UNIQ > $WORKDIR/all.inc
      ;;
   all)
      easylist
      easyprivacy
      nocoin
      rwtracker
      mwdomains
      convert
      $CAT $WORKDIR/easylist.inc $WORKDIR/easyprivacy.inc $WORKDIR/adguard.inc $WORKDIR/nocoin.inc $WORKDIR/rwtracker.inc $WORKDIR/malwaredomains.inc | $SORT | $UNIQ > $WORKDIR/all.inc
      ;;
esac
exit $RETVAL
