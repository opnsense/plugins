#!/bin/sh

board_id=$(smbmsg -f /dev/smb1 -s 0x62 -c 0x00 -i 1)

echo $board_id