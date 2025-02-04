#!/bin/sh
timestamp=$(date +%s)
/usr/local/etc/rc.d/netbird stop
echo "Deleting old configuration file"
mv /usr/local/etc/netbird/config.json /usr/local/etc/netbird/config.json.$timestamp
/usr/local/etc/rc.d/netbird start
/usr/local/bin/netbird up $@ 2>&1
if [ $? -ne 0 ]; then
    /usr/local/etc/rc.d/netbird stop
    echo "Failed to bring up netbird"
    echo "Restoring old configuration file"
    mv /usr/local/etc/netbird/config.json /usr/local/etc/netbird/config.json.$timestamp.fail
    mv /usr/local/etc/netbird/config.json.$timestamp /usr/local/etc/netbird/config.json
    /usr/local/etc/rc.d/netbird start
fi
exit 0