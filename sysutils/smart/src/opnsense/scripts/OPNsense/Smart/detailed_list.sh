#!/bin/sh

# Copyright (C) 2018 Smart-Soft
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

RESULT=

OIFS="$IFS"
IFS=$'\n' # use newline separator to get the whole smartctl device string
C=0
# Operate only devices that smartctl can handle. 
# This creates variables DEVICE_1 ... DEVICE_<number of devices>.
for I in $(/usr/local/sbin/smartctl --scan | /usr/bin/awk -F# '{print $1}'); do
   C=$(expr $C + 1)
   eval DEVICE_${C}="\$I";
done
IFS="$OIFS" # restore the previous IFS settings


for I in $(/usr/bin/seq 1 $C); do
   eval DEV="\$DEVICE_$I"

   STATE=$(/usr/local/sbin/smartctl -jH  ${DEV})

   # If there is no valid state, skip it
   if [ $? -ne 0 ]; then
      continue;
   fi

   if [ -n "${RESULT}" ]; then
      RESULT="${RESULT},";
   fi
   # get a valid identifier for the device, the serial number
   IDENT=$(/usr/local/sbin/smartctl -a  ${DEV} | /usr/bin/awk '/^Serial number:/{print $3}')

   RESULT="${RESULT}{\"device\":\"${DEV##*-d}\",\"ident\":\"${IDENT}\",\"state\":${STATE}}";

done

echo "[${RESULT}]"
