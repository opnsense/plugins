#!/bin/sh

set -eu

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

rc_name_is_set() {
    printf '%s\n' "$configured_names" | grep -Fqx "$1"
}

if ! configured_names=$(sysrc -s named -N -A)
then
    fail 'could not inspect the managed BIND rc configuration'
fi
if rc_name_is_set named_enable
then
    named_had_enable=yes
    if ! named_previous_enable=$(sysrc -s named -n named_enable)
    then
        fail 'could not read the managed BIND enable setting'
    fi
else
    named_had_enable=no
    named_previous_enable=
fi
if rc_name_is_set named_conf
then
    named_had_conf=yes
    if ! named_previous_conf=$(sysrc -s named -n named_conf)
    then
        fail 'could not read the managed BIND configuration setting'
    fi
else
    named_had_conf=no
    named_previous_conf=
fi

runtime_directory=$(mktemp -d /usr/local/etc/namedb/resolver-plugins-ci.XXXXXX)
named_enable_mutated=no
named_conf_mutated=no
service_started=no

# ShellCheck cannot infer that this function is invoked by the EXIT trap.
# shellcheck disable=SC2317
cleanup() {
    status=$?
    cleanup_failed=no
    trap - EXIT HUP INT TERM
    if [ "$service_started" = yes ]
    then
        if ! service named onestop >/dev/null 2>&1
        then
            cleanup_failed=yes
        fi
    fi
    if [ "$named_conf_mutated" = yes ] && [ "$named_had_conf" = yes ]
    then
        if ! sysrc -s named "named_conf=$named_previous_conf" >/dev/null
        then
            cleanup_failed=yes
        fi
    elif [ "$named_conf_mutated" = yes ]
    then
        if ! sysrc -s named -x named_conf >/dev/null 2>&1
        then
            cleanup_failed=yes
        fi
    fi
    if [ "$named_enable_mutated" = yes ] && [ "$named_had_enable" = yes ]
    then
        if ! sysrc -s named "named_enable=$named_previous_enable" >/dev/null
        then
            cleanup_failed=yes
        fi
    elif [ "$named_enable_mutated" = yes ]
    then
        if ! sysrc -s named -x named_enable >/dev/null 2>&1
        then
            cleanup_failed=yes
        fi
    fi
    if ! rm -f "$runtime_directory/named.conf" \
        "$runtime_directory/canary.invalid.zone" \
        "$runtime_directory/answer.txt" "$runtime_directory/named.pid" \
        "$runtime_directory/rndc.key" "$runtime_directory/session.key"
    then
        cleanup_failed=yes
    fi
    if ! rmdir "$runtime_directory" >/dev/null 2>&1
    then
        cleanup_failed=yes
    fi
    if [ "$cleanup_failed" = yes ] && [ "$status" -eq 0 ]
    then
        status=1
    fi
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
named_enable_mutated=yes
sysrc -s named named_enable=YES >/dev/null
named_conf_mutated=yes
sysrc -s named "named_conf=$runtime_directory/named.conf" >/dev/null
service_started=yes
service named onestart
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
