#!/bin/sh

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
