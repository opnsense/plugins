#!/bin/sh


if [ ! -z $1 ]; then
    #Check CPLD first to make sure we are on a VEP board
    board_id=$(smbmsg -f /dev/smb1 -s 0x62 -c 0x00 -i 1 | cut -c 4-5)

    dc=$(( $1 & 0x0f ))

    if [ x"$board_id" != x"0" ] && [ x"$board_id" != x"1" ]; then
        smbmsg -f /dev/smb1 -s 0x36 -c 0x06 -o 1 $dc
    fi
fi

