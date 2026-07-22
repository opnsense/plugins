#!/bin/sh

board_id=$(smbmsg -f /dev/smb1 -s 0x62 -c 0x00 -i 1 | cut -c 4-5)

if [ ! -z $board_id ]; then
    for fan in 0 1; do
        fan_speed_in_steps=$(smbmsg -f /dev/smb1 -s 0x36 -c $fan -i 1 -F "%d")
        echo fan$((fan + 1))=$(($fan_speed_in_steps * 50))
    done
fi