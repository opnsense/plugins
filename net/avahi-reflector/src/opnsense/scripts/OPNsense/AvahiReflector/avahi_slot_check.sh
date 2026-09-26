#!/bin/sh

# Copyright (C) 2026 cayossarian (Bill Flood)
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

# Monit custom check: detect avahi-daemon slot exhaustion
# Exit 0 = OK, Exit 1 = slot errors detected (Monit restarts avahi-daemon)
#
# The avahi-daemon reflector has a hardcoded 100-slot pool for legacy unicast
# reflection. Bursts of mDNS traffic can exhaust all slots, causing reflected
# services to appear offline for hours until manually restarted.
#
# avahi-daemon logs to its own file (templates/OPNsense/Syslog/local/avahi.conf).
# latest.log is a symlink kept by the hourly syslog archive job, so it is
# followed with stat -L; until that job first creates it, the newest dated
# file is used instead.

AVAHI_LOG="${AVAHI_LOG:-/var/log/avahi/latest.log}"
AVAHI_SLOT_STATE="${AVAHI_SLOT_STATE:-/var/run/avahi_slot_check.state}"
PATTERN="No slot available for legacy unicast reflection"

is_uint() {
    case "$1" in
        ''|*[!0-9]*) return 1 ;;
        *) return 0 ;;
    esac
}

LOG_FILE="$AVAHI_LOG"
if [ ! -e "$LOG_FILE" ]; then
    # glob expansion is sorted, so the last match is the newest dated file
    for DATED in "$(dirname "$AVAHI_LOG")"/avahi_*.log; do
        if [ -f "$DATED" ]; then
            LOG_FILE="$DATED"
        fi
    done
fi

if [ ! -f "$LOG_FILE" ]; then
    echo "OK - avahi log not found"
    exit 0
fi

CURRENT_INODE=$(stat -L -f %i "$LOG_FILE" 2>/dev/null)
FILE_SIZE=$(stat -L -f %z "$LOG_FILE" 2>/dev/null)
if ! is_uint "$CURRENT_INODE" || ! is_uint "$FILE_SIZE"; then
    echo "OK - cannot stat avahi log"
    exit 0
fi

LAST_OFFSET=0
if [ -f "$AVAHI_SLOT_STATE" ]; then
    SAVED_OFFSET=$(sed -n '1p' "$AVAHI_SLOT_STATE" 2>/dev/null)
    SAVED_INODE=$(sed -n '2p' "$AVAHI_SLOT_STATE" 2>/dev/null)
    # A corrupt state file counts as fresh. Rotation (a new inode) or a file
    # that shrank is read again from the start.
    if is_uint "$SAVED_OFFSET" && is_uint "$SAVED_INODE" && \
        [ "$SAVED_INODE" = "$CURRENT_INODE" ] && [ "$SAVED_OFFSET" -le "$FILE_SIZE" ]; then
        LAST_OFFSET="$SAVED_OFFSET"
    fi
fi

# Save current position for next run
printf '%s\n%s\n' "$FILE_SIZE" "$CURRENT_INODE" > "$AVAHI_SLOT_STATE"

BYTES_TO_READ=$((FILE_SIZE - LAST_OFFSET))
if [ "$BYTES_TO_READ" -le 0 ]; then
    echo "OK - No new data"
    exit 0
fi

# Exactly the bytes between the saved offset and the size just recorded;
# grep -a keeps stray binary bytes from turning the output into a
# "Binary file matches" notice.
MATCHES=$(tail -c +$((LAST_OFFSET + 1)) "$LOG_FILE" | head -c "$BYTES_TO_READ" | grep -a -F "$PATTERN")
if [ -n "$MATCHES" ]; then
    SLOT_COUNT=$(printf '%s\n' "$MATCHES" | wc -l | tr -d ' ')
    LAST_LINE=$(printf '%s\n' "$MATCHES" | tail -n 1)
    echo "CRITICAL - $SLOT_COUNT slot exhaustion events since last check"
    echo "  Last: $LAST_LINE"
    exit 1
fi

echo "OK - No slot errors"
exit 0
