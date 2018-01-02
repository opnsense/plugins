#!/bin/sh

# Copyright (C) 2017 Smart-Soft
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

KEYTAB=/usr/local/etc/squid/squid.keytab
PASS_TMP=/tmp/__tmp_kerb_pass

while getopts :d:n:k:e:b:u:p: name
do
    case $name in
        d) DOMAIN="$OPTARG" ;;              # aka opnsense.local
        n) PRINCIPAL="$OPTARG" ;;           # aka HTTP/OPNSENSE
        k) KERB_COMPUTER_NAME="$OPTARG" ;;  # aka OPNSENSE-K
        e) ENCTYPES="$OPTARG" ;;
        b) BASENAME="$OPTARG" ;;
        u) USERNAME="$OPTARG" ;;            # LDAP admin username
        p) PASSWORD="$OPTARG" ;;            # LDAP admin password
    esac
done

[ "$USERNAME" == "" ] && echo "No administrator account name" && exit 0;
[ "$PASSWORD" == "" ] && echo "No administrator account password" && exit 0;
[ "$BASENAME" == "" ] && BASENAME="CN=Computers";
[ "$PRINCIPAL" == "" ] && echo "No principal name" && exit 0;
[ "$DOMAIN" == "" ] && echo "No domain name" && exit 0;
[ "$KERB_COMPUTER_NAME" == "" ] && echo "No Kerberos name for host" && exit 0;
[ "$ENCTYPES" == "2008" ] && ENCTYPES_PARAM="--enctypes 28";


PASSWORD="${PASSWORD%\'}"
echo "${PASSWORD}" | sed 's/\\//g' > ${PASS_TMP}

#/usr/local/bin/kinit --password-file=${PASS_TMP} ${USERNAME}
/usr/local/bin/kinit ${USERNAME} < ${PASS_TMP}
TICKET=$?
rm ${PASS_TMP}
[ $TICKET != 0 ] && echo "No ticket" && exit 0;

/usr/local/sbin/msktutil -c --verbose -b "${BASENAME}"  -s ${PRINCIPAL}.${DOMAIN} -k ${KEYTAB} --computer-name ${KERB_COMPUTER_NAME} --upn ${PRINCIPAL}.${DOMAIN} ${ENCTYPES_PARAM} 2>&1

chmod +r ${KEYTAB}

/usr/local/bin/kdestroy
