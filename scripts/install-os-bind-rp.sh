#!/bin/sh

set -eu

readonly minimum_bind920_version=9.20.26
readonly public_key_sha256=bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e
readonly release_base=https://github.com/resolver-plugins/plugins/releases/download

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

package_field() {
    printf '%s\n' "$1" | awk -F '|' -v field="$2" 'NF == 3 { print $field; exit }'
}

package_description() {
    name=$(package_field "$1" 1)
    version=$(package_field "$1" 2)
    origin=$(package_field "$1" 3)
    if [ -n "$name" ] && [ -n "$version" ] && [ -n "$origin" ]
    then
        printf '%s %s from %s' "$name" "$version" "$origin"
    else
        printf '%s' 'not installed'
    fi
}

write_repository() {
    name=$1
    url=$2
    enabled=$3
    destination="$repository_directory/$name.conf"
    printf '%s\n' \
        "$name: {" \
        "  url: \"$url\"," \
        '  mirror_type: "none",' \
        '  signature_type: "pubkey",' \
        "  pubkey: \"$public_key\"," \
        "  enabled: $enabled" \
        '}' > "$destination"
}

series_from_opnsense_version() {
    product_version=$(opnsense-version 2>/dev/null | awk 'NR == 1 { print $2 }')
    series=$(printf '%s\n' "$product_version" | sed -E 's/^([0-9]+\.[0-9]+).*/\1/')
    case "$series" in
        26.1)
            case "$(pkg version -t "$product_version" '26.1.11_10')" in
                '='|'>') ;;
                *) fail "OPNsense 26.1.11_10 or newer is required (installed: $product_version)" ;;
            esac
            printf '%s\n' "$series"
            ;;
        26.7)
            printf '%s\n' "$series"
            ;;
        *)
            fail "unsupported OPNsense release series: ${series:-unknown}"
            ;;
    esac
}

bind920_is_eligible() {
    bind920_name=$(package_field "$bind920" 1)
    bind920_version=$(package_field "$bind920" 2)
    bind920_origin=$(package_field "$bind920" 3)
    bind_tools_name=$(package_field "$bind_tools" 1)
    bind_tools_origin=$(package_field "$bind_tools" 3)

    [ "$bind920_name" = bind920 ] || return 1
    [ "$bind920_origin" = dns/bind920 ] || return 1
    [ "$bind_tools_name" = bind-tools ] || return 1
    [ "$bind_tools_origin" = dns/bind-tools ] || return 1
    case "$(pkg version -t "$bind920_version" "$minimum_bind920_version")" in
        '='|'>') return 0 ;;
        *) return 1 ;;
    esac
}

repository_directory=${RP_PKG_REPOSITORY_DIR:-/usr/local/etc/pkg/repos}
key_directory=${RP_PKG_KEYS_DIR:-/usr/local/etc/pkg/keys}
temporary_directory=${RP_TEMPORARY_DIRECTORY:-}
cleanup_temporary_directory=no
if [ -z "$temporary_directory" ]
then
    temporary_directory=$(mktemp -d)
    cleanup_temporary_directory=yes
fi
mkdir -p "$repository_directory" "$key_directory" "$temporary_directory"
if [ "$cleanup_temporary_directory" = yes ]
then
    trap 'rm -rf "$temporary_directory"' EXIT HUP INT TERM
fi

series=$(series_from_opnsense_version)
current_channel=pkg-$series
public_key="$key_directory/resolver-plugins.pub"
public_key_candidate="$temporary_directory/resolver-plugins.pub"

fetch -o "$public_key_candidate" "$release_base/$current_channel/resolver-plugins.pub"
[ "$(sha256 -q "$public_key_candidate")" = "$public_key_sha256" ] || \
    fail 'resolver-plugins public-key fingerprint verification failed'
install -m 0644 "$public_key_candidate" "$public_key"

write_repository resolver-plugins "$release_base/$current_channel" yes
pkg update -r resolver-plugins

bind920=$(pkg query -e '%n = bind920' '%n|%v|%o' 2>/dev/null || true)
bind_tools=$(pkg query -e '%n = bind-tools' '%n|%v|%o' 2>/dev/null || true)

if ! bind920_is_eligible
then
    fallback_repository=resolver-plugins-bind920
    fallback_channel=pkg-$series-bind920
    write_repository "$fallback_repository" "$release_base/$fallback_channel" no
    pkg update -r "$fallback_repository"
    fallback_packages=$(pkg rquery -r "$fallback_repository" '%n|%v|%o') || \
        fail 'could not inspect the Resolver BIND fallback repository'
    fallback_bind920=$(printf '%s\n' "$fallback_packages" | awk -F '|' '$1 == "bind920" { print; exit }')
    fallback_bind_tools=$(printf '%s\n' "$fallback_packages" | awk -F '|' '$1 == "bind-tools" { print; exit }')
    fallback_bind920_version=$(package_field "$fallback_bind920" 2)
    fallback_bind_tools_version=$(package_field "$fallback_bind_tools" 2)
    [ -n "$fallback_bind920_version" ] && [ -n "$fallback_bind_tools_version" ] || \
        fail 'Resolver BIND fallback repository is incomplete'

    printf '%s\n' "Installed bind920: $(package_description "$bind920")" >&2
    printf '%s\n' "Installed bind-tools: $(package_description "$bind_tools")" >&2
    printf '%s\n' \
        "Available fallback: bind920 $fallback_bind920_version and bind-tools $fallback_bind_tools_version" >&2
    printf '%s\n' 'An update to BIND is required to address a breaking issue with DoT.' >&2
    printf '%s\n' 'Note: future OPNsense updates to BIND will still work as long as they are above the pinned version.' >&2
    printf '%s' 'Do you wish to update BIND? [y/N] ' >&2
    tty_path=${RP_TTY_PATH:-/dev/tty}
    [ -r "$tty_path" ] || fail 'a terminal is required to approve the BIND update'
    if ! IFS= read -r response < "$tty_path"
    then
        response=
    fi
    case "$response" in
        y|Y|yes|YES|Yes)
            pkg install -y -r "$fallback_repository" bind920 bind-tools
            bind920=$(pkg query -e '%n = bind920' '%n|%v|%o' 2>/dev/null || true)
            bind_tools=$(pkg query -e '%n = bind-tools' '%n|%v|%o' 2>/dev/null || true)
            bind920_is_eligible || fail 'Resolver BIND fallback did not satisfy plugin requirements'
            ;;
        *)
            fail 'BIND update declined; os-bind-rp was not installed.'
            ;;
    esac
fi

pkg install -y -r resolver-plugins os-bind-rp
