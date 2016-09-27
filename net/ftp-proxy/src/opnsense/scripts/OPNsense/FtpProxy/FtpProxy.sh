#!/bin/sh
#
# Copyright (C) 2016 EURO-LOG AG
#
#     All rights reserved.
#
#     Redistribution and use in source and binary forms, with or without
#     modification, are permitted provided that the following conditions are met:
#
#     1. Redistributions of source code must retain the above copyright notice,
#        this list of conditions and the following disclaimer.
#
#     2. Redistributions in binary form must reproduce the above copyright
#        notice, this list of conditions and the following disclaimer in the
#        documentation and/or other materials provided with the distribution.
#
#     THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
#     INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
#     AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
#     AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
#     OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
#     SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
#     INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
#     CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
#     ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
#     POSSIBILITY OF SUCH DAMAGE.
#

ACTION=$1
shift
FLAGS=$@

# determine listenaddress and listenport to identify ftp-proxy process
for FLAG in $FLAGS; do
   if [ "$FLAG" == "-b" ]; then
      NEXT_FLAG="LISTENADDRESS"
      continue
   fi
   if [ "$FLAG" == "-p" ]; then
      NEXT_FLAG="LISTENPORT"
      continue
   fi
   if [ "X$NEXT_FLAG" != "X" ]; then
       if [ "$NEXT_FLAG" == "LISTENADDRESS" -a "X$FLAG" != "X" ]; then
          LISTENADDRESS=$FLAG
          NEXT_FLAG=""
       fi
       if [ "$NEXT_FLAG" == "LISTENPORT" -a "X$FLAG" != "X" ]; then
          LISTENPORT=$FLAG
          NEXT_FLAG=""
       fi
   fi
   if [ "X$LISTENADDRESS" != "X" -a "X$LISTENPORT" != "X" ]; then
      break
   fi
done

if [ "X$LISTENADDRESS" == "X" -o "X$LISTENPORT" == "X" ]; then
   ( >&2 echo "Either listenaddress or listenport not given. Check -b and -p flags." )
    exit 999
fi

ftpproxy_start () {
   ftpproxy_status
   if [ $? -gt 0 ]; then # already running
      return 0
   fi

   /usr/sbin/ftp-proxy $FLAGS
   return $?
}

ftpproxy_stop () {
   ftpproxy_status
   PID=$?
   if [ $PID -eq 0 ]; then # already stopped
      return 0
   fi
   kill $PID
   return $?
}

ftpproxy_restart () {
   ftpproxy_stop
   if [ $? -ne 0 ]; then
      return $?
   fi
   ftpproxy_start
   return $?
}

ftpproxy_status () {
   PID=`ps ax -o pid= -o command= | grep "/usr/sbin/ftp-proxy -b $LISTENADDRESS -p $LISTENPORT" | grep -v grep | awk '{ print $1 }'`
   if [ "X$PID" != "X" ]; then
      return $PID
   fi
   return 0
}

case $ACTION in
   start)
      ftpproxy_start
      exit $?
      ;;
   stop)
      ftpproxy_stop
      exit $?
      ;;
   restart)
      ftpproxy_restart
      exit $?
      ;;
   status)
      ftpproxy_status
      if [ $? -gt 0 ]; then
         exit 0
      fi
      exit 1
      ;;
esac
