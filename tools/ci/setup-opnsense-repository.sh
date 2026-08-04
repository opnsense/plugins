#!/bin/sh

set -eu

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

[ "$#" -eq 1 ] || fail "usage: $0 <26.1|26.7>"

series=$1
case "$series" in
    26.1)
        expected_archive_sha256=95cb9d549165520de984adbe7bd740ca237dd470b779d7ef3706d5f11b8c321e
        ;;
    26.7)
        expected_archive_sha256=2706f4a078b60db164fb1f0a98f5e89ead7c3b77aee35e6d9ff77b65338d29e3
        ;;
    *) fail "unsupported OPNsense series: $series" ;;
esac

metadata_field() {
    python3 - "$RP_UPSTREAM_METADATA" "$series" "$1" <<'PY'
import json
import sys

metadata_path, series, field = sys.argv[1:]
required_fields = (
    'series',
    'upstream_branch',
    'upstream_commit',
    'freebsd_release',
    'core_commit',
    'core_archive_url',
    'core_archive_sha256',
)
try:
    with open(metadata_path, encoding='utf-8') as metadata_file:
        metadata = json.load(metadata_file)
except (OSError, json.JSONDecodeError) as error:
    raise SystemExit(f'cannot read upstream metadata: {error}')

if not isinstance(metadata, dict):
    raise SystemExit('upstream metadata must be a JSON object')
for required_field in required_fields:
    if not isinstance(metadata.get(required_field), str) or not metadata[required_field]:
        raise SystemExit(f'upstream metadata has an invalid {required_field}')
if metadata['series'] != series:
    raise SystemExit(
        f'upstream metadata series {metadata["series"]} does not match {series}'
    )
if metadata['core_archive_url'] != (
    'https://github.com/opnsense/core/archive/'
    f'{metadata["core_commit"]}.tar.gz'
):
    raise SystemExit('upstream metadata core archive URL is not immutable')
print(metadata[field])
PY
}

if [ -n "${RP_UPSTREAM_METADATA:-}" ]
then
    expected_archive_sha256=$(metadata_field core_archive_sha256) || \
        fail 'invalid upstream metadata'
    archive_url=$(metadata_field core_archive_url) || fail 'invalid upstream metadata'
else
    archive_url=${OPNSENSE_CORE_ARCHIVE_URL:-https://github.com/opnsense/core/archive/refs/heads/stable/$series.tar.gz}
fi

repository_directory=${PKG_REPOS_DIR:-/usr/local/etc/pkg/repos}
fingerprint_directory=${PKG_FINGERPRINTS_DIR:-/usr/local/etc/pkg/fingerprints/OPNsense}
fetch_command=${FETCH_COMMAND:-fetch}
temporary_directory=$(mktemp -d)
archive_path="$temporary_directory/core.tar.gz"
checkout_directory="$temporary_directory/core"
trap 'rm -rf "$temporary_directory"' EXIT HUP INT TERM

"$fetch_command" -q -o "$archive_path" "$archive_url"
if [ -n "${SHA256_COMMAND:-}" ]
then
    archive_sha256=$("$SHA256_COMMAND" "$archive_path")
elif command -v sha256 >/dev/null 2>&1
then
    archive_sha256=$(sha256 -q "$archive_path")
elif command -v sha256sum >/dev/null 2>&1
then
    archive_sha256=$(sha256sum "$archive_path" | awk '{print $1}')
else
    fail 'no SHA-256 command is available'
fi
[ "$archive_sha256" = "$expected_archive_sha256" ] || \
    fail "OPNsense core archive does not match the pinned SHA-256 for $series"
mkdir -p "$checkout_directory"
tar -xzf "$archive_path" -C "$checkout_directory"
set -- "$checkout_directory"/*
[ "$#" -eq 1 ] || fail 'OPNsense core archive has an unexpected layout'
core_directory=$1

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

printf '%s\n' "$archive_sha256"
