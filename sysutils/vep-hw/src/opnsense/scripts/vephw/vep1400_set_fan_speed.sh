#!/bin/sh


if [ ! -z $1 ]; then
    #Check CPLD first to make sure we are on a VEP board
    board_id=$(smbmsg -f /dev/smb1 -s 0x62 -c 0x00 -i 1 | cut -c 4-5)
    mode=$1
    current_control_reg = $(smbmsg -f /dev/smb1 -s 0x36 -c 0x04 -i 1)
    if [ x"$mode" == x"0" ]; then #auto mode
        if [ "$current_control_reg" != "0x14" ]; then
            # if we are not already in auto mode, then switch
            smbmsg -f /dev/smb1 -s 0x36 -c 0x04 -o 1 0x14
        fi
    elif [ x"$mode" == x"1" ]; then #manual mode
        if [ "$current_control_reg" != "0x34" ]; then
            # if we are not already in manual mode, then switch
            smbmsg -f /dev/smb1 -s 0x36 -c 0x04 -o 1 0x34
        fi
        if [ ! -z $2]; then
            dc=$(( $2 & 0x0f )) #keep only the lowest nibble, the register only goes up to 0x0f

            if [ x"$board_id" != x"0" ] && [ x"$board_id" != x"1" ]; then
                smbmsg -f /dev/smb1 -s 0x36 -c 0x06 -o 1 $dc
            fi
        else
            echo "No duty cycle specified. Using previous."
        fi
    fi
fi

