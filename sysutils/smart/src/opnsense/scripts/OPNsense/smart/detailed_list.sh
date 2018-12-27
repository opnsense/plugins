#!/bin/sh

result=""

for dev in `ls /dev | grep '^\(ad\|da\|ada\)[0-9]\{1,2\}$'`; do
    ident=`/usr/sbin/diskinfo -v $dev | grep ident | awk '{print $1}'`;
    state=`/usr/local/sbin/smartctl -H $dev | awk -F: '
/^SMART overall-health self-assessment test result/ {print $2;exit}
/^SMART Health Status/ {print $2;exit}'`;

    if [ -n "$result" ]; then
	result="$result,";
    fi

    result="$result{\"device\":\"$dev\",\"ident\":\"$ident\",\"state\":\"$state\"}";
done

echo "[$result]"
