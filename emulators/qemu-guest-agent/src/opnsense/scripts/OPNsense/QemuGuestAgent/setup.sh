#!/bin/sh

# Check if the kernel module exists
KERNMOD='virtio_console'
if [ -e /boot/kernel/${KERNMOD}.ko ]; then
    # Load module
    kldload $KERNMOD 2>&1
fi

exit 0
