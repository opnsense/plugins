#!/bin/sh
# Monit custom check: detect avahi-daemon slot exhaustion
# Exit 0 = OK, Exit 1 = slot errors detected (Monit restarts avahi-daemon)
#
# The avahi-daemon reflector has a hardcoded 100-slot pool for legacy unicast
# reflection. Bursts of mDNS traffic can exhaust all slots, causing reflected
# services to appear offline for hours until manually restarted.

LOG_FILE="/var/log/system/latest.log"
STATE_FILE="/var/run/avahi_slot_check.state"
PATTERN="No slot available for legacy unicast reflection"

if [ ! -f "$LOG_FILE" ]; then
    echo "OK - syslog not found"
    exit 0
fi

CURRENT_INODE=$(stat -f %i "$LOG_FILE" 2>/dev/null)
FILE_SIZE=$(stat -f %z "$LOG_FILE" 2>/dev/null)
if [ -z "$FILE_SIZE" ] || [ -z "$CURRENT_INODE" ]; then
    echo "OK - cannot stat syslog"
    exit 0
fi

LAST_OFFSET=0
LAST_INODE=0
if [ -f "$STATE_FILE" ]; then
    LAST_OFFSET=$(sed -n '1p' "$STATE_FILE")
    LAST_INODE=$(sed -n '2p' "$STATE_FILE")
    # Handle log rotation: reset if inode changed or file shrank
    if [ "$CURRENT_INODE" != "$LAST_INODE" ] || [ "$FILE_SIZE" -lt "$LAST_OFFSET" ]; then
        LAST_OFFSET=0
    fi
fi

# Save current position for next run
printf '%s\n%s\n' "$FILE_SIZE" "$CURRENT_INODE" > "$STATE_FILE"

BYTES_TO_READ=$((FILE_SIZE - LAST_OFFSET))
if [ "$BYTES_TO_READ" -le 0 ]; then
    echo "OK - No new data"
    exit 0
fi

NEW_DATA=$(tail -c "$BYTES_TO_READ" "$LOG_FILE")
SLOT_COUNT=$(echo "$NEW_DATA" | grep -c "$PATTERN")

if [ "$SLOT_COUNT" -gt 0 ]; then
    LAST_LINE=$(echo "$NEW_DATA" | grep "$PATTERN" | tail -1)
    echo "CRITICAL - $SLOT_COUNT slot exhaustion events since last check"
    echo "  Last: $LAST_LINE"
    exit 1
else
    echo "OK - No slot errors"
    exit 0
fi
