#!/bin/sh

rc=/usr/local/etc/rc.d/yggdrasil
conf=/usr/local/etc/yggdrasil.conf

is_stopped()
{
    [ "$("$rc" onestatus 2>/dev/null)" = "yggdrasil is not running." ]
}

is_stopped && exit 0

"$rc" onestop
result=$?

if [ "$result" -ne 0 ]; then
    grep -Eq '^[[:space:]]*IfName:[[:space:]]*(auto|none)[[:space:]]*$' "$conf" ||
        exit "$result"
fi

for attempt in 1 2 3 4 5; do
    is_stopped && exit 0
    sleep 1
done

is_stopped
