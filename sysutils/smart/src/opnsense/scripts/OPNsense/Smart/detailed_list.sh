#!/bin/sh

# Copyright (C) 2019 Smart-Soft
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

for dev in `ls /dev | grep '^nvme[0-9]\+$'`; do
    ident=`/usr/local/sbin/smartctl -a /dev/$dev | grep 'Serial Number' | awk -F ': *' '{print $2}'`;
    state=`/usr/local/sbin/smartctl -H $dev | awk -F: '
/^SMART overall-health self-assessment test result/ {print $2;exit}
/^SMART Health Status/ {print $2;exit}'`;

    if [ -n "$result" ]; then
	result="$result,";
    fi

    result="$result{\"device\":\"$dev\",\"ident\":\"$ident\",\"state\":\"$state\"}";
done

echo "[$result]"
