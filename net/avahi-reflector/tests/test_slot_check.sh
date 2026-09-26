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

# Tests for avahi_slot_check.sh against temporary files. Needs a BSD stat
# (FreeBSD or macOS). Run: sh tests/test_slot_check.sh

HERE=$(cd "$(dirname "$0")" && pwd)
CHECK="$HERE/../src/opnsense/scripts/OPNsense/AvahiReflector/avahi_slot_check.sh"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

SLOT='<28>1 2026-09-24T10:30:45-07:00 OPNsense.example.arpa avahi-daemon 86657 - [meta sequenceId="1"] No slot available for legacy unicast reflection.'
OTHER='<30>1 2026-09-24T10:31:00-07:00 OPNsense.example.arpa avahi-daemon 86657 - [meta sequenceId="2"] Joining mDNS multicast group.'

PASS=0
FAIL=0
OUT=""
RC=0

run_check() {
    OUT=$(AVAHI_LOG="$AVAHI_LOG" AVAHI_SLOT_STATE="$AVAHI_SLOT_STATE" sh "$CHECK")
    RC=$?
}

expect() {
    # expect <description> <exit code> <output prefix>
    if [ "$RC" -eq "$2" ] && case "$OUT" in "$3"*) true ;; *) false ;; esac; then
        PASS=$((PASS + 1))
        echo "ok   - $1"
    else
        FAIL=$((FAIL + 1))
        echo "FAIL - $1: rc=$RC (want $2) output: $OUT"
    fi
}

reset() {
    rm -rf "${WORK:?}"/*
    mkdir -p "$WORK/log"
    AVAHI_SLOT_STATE="$WORK/state"
}

# 1. Plain file: a new matching line is CRITICAL, then no new data is OK.
reset
AVAHI_LOG="$WORK/log/avahi_20260924.log"
printf '%s\n' "$OTHER" > "$AVAHI_LOG"
run_check; expect "unrelated line only is OK" 0 "OK"
run_check; expect "no new data is OK" 0 "OK - No new data"
printf '%s\n' "$SLOT" >> "$AVAHI_LOG"
run_check; expect "appended slot line is CRITICAL" 1 "CRITICAL - 1 slot exhaustion"
run_check; expect "same line is not reported twice" 0 "OK - No new data"
printf '%s\n' "$OTHER" >> "$AVAHI_LOG"
run_check; expect "appended unrelated line is OK" 0 "OK - No slot errors"

# 2. latest.log is a symlink: the check must follow it (stat -L).
reset
printf '%s\n' "$OTHER" > "$WORK/log/avahi_20260924.log"
ln -s "$WORK/log/avahi_20260924.log" "$WORK/log/latest.log"
AVAHI_LOG="$WORK/log/latest.log"
run_check; expect "symlink: first run is OK" 0 "OK"
printf '%s\n' "$SLOT" >> "$WORK/log/avahi_20260924.log"
run_check; expect "symlink: appended slot line is CRITICAL" 1 "CRITICAL - 1 slot exhaustion"
run_check; expect "symlink: no new data is OK" 0 "OK - No new data"

# 3. Symlink retargeted to a new day's file (rotation): read the new file from the start.
printf '%s\n' "$SLOT" "$SLOT" > "$WORK/log/avahi_20260925.log"
rm "$WORK/log/latest.log"
ln -s "$WORK/log/avahi_20260925.log" "$WORK/log/latest.log"
run_check; expect "rotation: new file is read from the start" 1 "CRITICAL - 2 slot exhaustion"

# 4. latest.log not created yet: fall back to the newest dated file.
reset
printf '%s\n' "$OTHER" > "$WORK/log/avahi_20260923.log"
printf '%s\n' "$SLOT" > "$WORK/log/avahi_20260924.log"
AVAHI_LOG="$WORK/log/latest.log"
run_check; expect "missing latest.log: newest dated file is checked" 1 "CRITICAL - 1 slot exhaustion"

# 5. No log at all is OK.
reset
AVAHI_LOG="$WORK/log/latest.log"
run_check; expect "no log file is OK" 0 "OK - avahi log not found"

# 6. Corrupt state file: treated as fresh, then rewritten valid.
reset
AVAHI_LOG="$WORK/log/avahi_20260924.log"
printf '%s\n' "$SLOT" > "$AVAHI_LOG"
printf 'garbage\n12abc\n' > "$AVAHI_SLOT_STATE"
run_check; expect "corrupt state: read from the start" 1 "CRITICAL - 1 slot exhaustion"
run_check; expect "corrupt state: rewritten, no new data" 0 "OK - No new data"
printf '' > "$AVAHI_SLOT_STATE"
run_check; expect "empty state: read from the start" 1 "CRITICAL - 1 slot exhaustion"
printf '99999999999\n1\n' > "$AVAHI_SLOT_STATE"
run_check; expect "state offset past end of file: read from the start" 1 "CRITICAL - 1 slot exhaustion"

# 7. Undecodable bytes around a match still count.
reset
AVAHI_LOG="$WORK/log/avahi_20260924.log"
printf '\377\376\000binary\n%s\n' "$SLOT" > "$AVAHI_LOG"
run_check; expect "binary bytes do not hide a match" 1 "CRITICAL - 1 slot exhaustion"

echo ""
echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
