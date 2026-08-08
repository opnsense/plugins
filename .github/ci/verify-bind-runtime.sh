#!/bin/sh

set -eu

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

runtime_directory=$(mktemp -d /usr/local/etc/namedb/resolver-plugins-ci.XXXXXX)
named_was_enabled=$(sysrc -n named_enable 2>/dev/null || true)
named_had_flags=$(sysrc -n named_flags >/dev/null 2>&1 && printf yes || printf no)
named_previous_flags=$(sysrc -n named_flags 2>/dev/null || true)
service_started=no

# ShellCheck cannot infer that this function is invoked by the EXIT trap.
# shellcheck disable=SC2317
cleanup() {
    status=$?
    trap - EXIT HUP INT TERM
    if [ "$service_started" = yes ]
    then
        service named onestop >/dev/null 2>&1 || true
    fi
    if [ "$named_had_flags" = yes ]
    then
        sysrc "named_flags=$named_previous_flags" >/dev/null
    else
        sysrc -x named_flags >/dev/null 2>&1 || true
    fi
    if [ -n "$named_was_enabled" ]
    then
        sysrc "named_enable=$named_was_enabled" >/dev/null
    else
        sysrc -x named_enable >/dev/null 2>&1 || true
    fi
    rm -f "$runtime_directory/named.conf" "$runtime_directory/canary.invalid.zone" \
        "$runtime_directory/answer.txt" "$runtime_directory/named.pid" \
        "$runtime_directory/session.key"
    rmdir "$runtime_directory" >/dev/null 2>&1 || true
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM

cat > "$runtime_directory/named.conf" <<EOF
options {
    directory "$runtime_directory";
    listen-on port 15353 { 127.0.0.1; };
    listen-on-v6 { none; };
    recursion yes;
    dnssec-validation no;
    pid-file "$runtime_directory/named.pid";
    session-keyfile "$runtime_directory/session.key";
};
controls { };
zone "canary.invalid" {
    type primary;
    file "canary.invalid.zone";
};
EOF
cat > "$runtime_directory/canary.invalid.zone" <<'EOF'
$TTL 60
@ IN SOA ns.canary.invalid. hostmaster.canary.invalid. 1 60 60 60 60
  IN NS ns.canary.invalid.
ns IN A 192.0.2.53
@  IN A 192.0.2.53
EOF
chown -R bind:bind "$runtime_directory"
chmod 0755 "$runtime_directory"
chmod 0644 "$runtime_directory/named.conf" "$runtime_directory/canary.invalid.zone"

named-checkconf "$runtime_directory/named.conf"
sysrc named_enable=YES >/dev/null
sysrc "named_flags=-c $runtime_directory/named.conf" >/dev/null
service named onestart
service_started=yes
service named onerestart

attempt=1
while [ "$attempt" -le 10 ]
do
    if drill -p 15353 canary.invalid @127.0.0.1 A > "$runtime_directory/answer.txt" 2>&1 && \
        grep -q '192\.0\.2\.53' "$runtime_directory/answer.txt"
    then
        exit 0
    fi
    sleep 1
    attempt=$((attempt + 1))
done
fail 'managed BIND runtime did not answer the canary query'
