#!/bin/sh

. /usr/local/etc/vephw/vephw.conf
#Check CPLD first to make sure we are on a VEP board
board_id=$(smbmsg -f /dev/smb1 -s 0x62 -c 0x00 -i 1 | cut -c 4-5)

if [ ! -z $board_id ]; then
    smbmsg -f /dev/smb1 -s 0x36 -c 0x06 -o 1 $FANDC
fi

