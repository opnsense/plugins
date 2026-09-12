#!/bin/sh

board_id=$(smbmsg -f /dev/smb1 -s 0x62 -c 0x00 -i 1 | cut -c 4-5)

if [ ! -z $board_id ] && [ ! -z $1 ] && [ ! -z $2 ] && [ ! -z $3 ]; then
    red=$(( $1 & 0xff ))
    green=$(( $2 & 0xff ))
    blue=$(( $3 & 0xff ))
    # set front LED to green
    # format is -c (red) -o 2 (green) (blue) (some models lack a blue led)
    smbmsg -f /dev/smb1 -s 0x40 -c $red -o 2 $green $blue
fi
