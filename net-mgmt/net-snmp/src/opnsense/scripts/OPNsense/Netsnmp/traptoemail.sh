#!/bin/sh

# This is a customized snmptrapd handler script to convert snmp traps into email
# messages. All formatting is done in snmptrapd.conf: "format execute"

usage()
{
    echo "Usage:"
    echo " Put a line like the following in your snmptrapd.conf file:"
    echo "  traphandle TRAPOID|default /usr/local/bin/traptoemail.sh [-f FROM] [-s SMTPSERVER] ADDRESS(ES)"
    echo "     FROM defaults to root"
    echo "     SMTPSERVER defaults to localhost"
}

FROM=root
SMTPSERVER=localhost

while [ $# -gt 0 ] ; do
	case "$1" in
		-f)
			if [ "${2#-}" != "${2}" -o -z "$2" ] ; then
				echo "ERROR: missing argument for ${1}"
				usage
				exit 1
			fi
			FROM=$2
			shift
            shift
			;;
		-s)
			if [ "${2#-}" != "${2}" -o -z "$2" ] ; then
				echo "ERROR: missing argument for ${1}"
				usage
				exit 1
			fi
			SMTPSERVER=$2
			shift
            shift
			;;
        *)
            break  
            ;;
    esac
done

if [ $# -eq 0 ] ; then
	echo "ERROR: missing email address(es)"
	usage
	exit 1
else
    RECIPIENTLIST=$(echo "$@" | sed  's/ /,/g')
    RECIPIENTS=$(echo "$@" | sed  's/ / --mail-rcpt /')
fi

C=0
TEXT=""

# process the trap:
while IFS='$\n' read -r line ; do
    C=$((C+1))
    case $C in
        1)
            SUBJECT=$line
            ;;
        2)
            IP=$line
            ;;
        *)
            TEXT="${TEXT}${line}\n"
            ;;
    esac
done

MSG=$(mktemp)
printf "From: ${FROM}\n" > "$MSG"
printf "To: ${RECIPIENTLIST}\n" >> "$MSG"
printf "Date: $(date)\n" >> "$MSG"
printf "Subject: trap ${SUBJECT} received from ${IP}\n" >> "$MSG"
printf "\n$TEXT" >> "$MSG"

curl -s smtp://${SMTPSERVER} --mail-from "${FROM}" --mail-rcpt ${RECIPIENTS} --upload-file "${MSG}"

rm -f "${MSG}"