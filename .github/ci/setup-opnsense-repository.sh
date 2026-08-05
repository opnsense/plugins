#!/bin/sh

set -eu

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

[ "$#" -eq 1 ] || fail "usage: $0 <26.1|26.7>"

series=$1
if [ -z "${RP_UPSTREAM_METADATA:-}" ]
then
    fail 'RP_UPSTREAM_METADATA is required'
fi

script_directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
metadata_field() {
    python3 "$script_directory/metadata_profile.py" \
        "$RP_UPSTREAM_METADATA" "$series" "$1"
}

core_commit=$(metadata_field core_commit) || fail 'invalid upstream metadata'

repository_directory=${PKG_REPOS_DIR:-/usr/local/etc/pkg/repos}
fingerprint_directory=${PKG_FINGERPRINTS_DIR:-/usr/local/etc/pkg/fingerprints/OPNsense}
git_command=${GIT_COMMAND:-git}
core_repository=${OPNSENSE_CORE_REPOSITORY:-https://github.com/opnsense/core.git}
temporary_directory=$(mktemp -d)
checkout_directory="$temporary_directory/core"
trap 'rm -rf "$temporary_directory"' EXIT HUP INT TERM

"$git_command" init "$checkout_directory" >/dev/null
"$git_command" -C "$checkout_directory" remote add origin "$core_repository"
"$git_command" -C "$checkout_directory" fetch --depth=1 origin "$core_commit"
"$git_command" -C "$checkout_directory" checkout --detach FETCH_HEAD >/dev/null
resolved_commit=$("$git_command" -C "$checkout_directory" rev-parse HEAD)
[ "$resolved_commit" = "$core_commit" ] || \
    fail "OPNsense core checkout does not match the pinned commit for $series"
core_directory=$checkout_directory

repository_template="$core_directory/src/etc/pkg/repos/OPNsense.conf.shadow.in"
fingerprints_source="$core_directory/src/etc/pkg/fingerprints/OPNsense"
[ -f "$repository_template" ] || fail 'OPNsense repository template is missing'
[ -d "$fingerprints_source/trusted" ] || fail 'OPNsense trusted fingerprints are missing'

repository_source="$temporary_directory/OPNsense.conf"
sed \
    -e 's|%%CORE_PACKAGESITE%%|https://pkg.opnsense.org|g' \
    -e "s|%%CORE_ABI%%|$series|g" \
    "$repository_template" > "$repository_source"
grep -Fq "https://pkg.opnsense.org/\${ABI}/$series/latest" "$repository_source" || \
    fail "OPNsense repository configuration does not target $series"
grep -Fq 'signature_type: "fingerprints"' "$repository_source" || \
    fail 'OPNsense repository configuration does not require fingerprints'

mkdir -p "$repository_directory" "$fingerprint_directory"
install -m 0644 "$repository_source" "$repository_directory/OPNsense.conf"
printf '%s\n' 'FreeBSD: {' '  enabled: no' '}' > "$repository_directory/FreeBSD.conf"

for group in trusted revoked
do
    source_directory="$fingerprints_source/$group"
    [ -d "$source_directory" ] || continue
    destination_directory="$fingerprint_directory/$group"
    mkdir -p "$destination_directory"
    for fingerprint in "$source_directory"/*
    do
        [ -f "$fingerprint" ] || continue
        install -m 0644 "$fingerprint" "$destination_directory/$(basename "$fingerprint")"
    done
done

set -- "$fingerprint_directory/trusted"/*
[ -f "$1" ] || fail 'no trusted OPNsense fingerprint was installed'

printf '%s\n' "$resolved_commit"
