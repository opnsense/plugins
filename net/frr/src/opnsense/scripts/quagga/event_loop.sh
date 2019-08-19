#!/bin/sh
while read line ; do
    /usr/local/sbin/configctl interface update carp service_status
done
