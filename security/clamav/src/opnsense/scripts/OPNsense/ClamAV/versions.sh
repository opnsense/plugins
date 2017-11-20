#!/bin/sh

echo -n "{"
clamconf | sed -n '/Database information/,/^$/p' | tail +2 | grep : | while read line ; do key=`echo  $line | cut -d':' -f1`; value=`echo  $line | cut -d':' -f2-` ; echo "\"$key\": \"$value\""; done | tr "\n", ","
clamconf | sed -n '/Software settings/,/^$/p' | tail +2 | grep : | while read line ; do key=`echo  $line | cut -d':' -f1`; value=`echo  $line | cut -d':' -f2-` ; echo "\"$key\": \"$value\""; done | tr "\n", "," | sed 's/,$//'
echo "}"
