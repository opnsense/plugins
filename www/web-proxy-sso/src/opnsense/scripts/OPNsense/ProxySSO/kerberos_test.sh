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

PASS_TMP=/tmp/__tmp_kerb_pass

while getopts :f:u:p: name
do
    case $name in
    f) FQDN="$OPTARG" ;;		# aka TING.tingnet.local
    u) USERNAME="$OPTARG" ;;		# username
    p) PASSWORD="$OPTARG" ;;		# password
    esac
done

[ "$USERNAME" == "" ] && echo "No account name" && exit 0;
[ "$PASSWORD" == "" ] && echo "No account password" && exit 0;
[ "$FQDN" == "" ] && echo "No FQDN" && exit 0;

PASSWORD="${PASSWORD%\'}"
echo "${PASSWORD}" | sed 's/\\//g' > ${PASS_TMP}

/usr/local/bin/kinit ${USERNAME} < ${PASS_TMP}
TICKET=$?
rm ${PASS_TMP}

/usr/local/libexec/squid/negotiate_kerberos_auth_test ${FQDN} | awk '{sub(/Token:/,"YR"); print $0}END{print "QQ"}' | /usr/local/libexec/squid/negotiate_kerberos_auth -s GSS_C_NO_NAME

/usr/local/bin/kdestroy

exit 0
