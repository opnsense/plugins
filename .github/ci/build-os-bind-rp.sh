#!/bin/sh

set -eu

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

[ "$#" -eq 2 ] || fail "usage: $0 <26.1|26.7> <artifact-directory>"

series=$1
artifact_directory=$2
if [ -z "${RP_UPSTREAM_METADATA:-}" ]
then
    fail 'RP_UPSTREAM_METADATA is required'
fi

script_directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repository_root=$(CDPATH= cd -- "$script_directory/../.." && pwd)
pkg_command=${PKG_COMMAND:-pkg}
make_command=${MAKE_COMMAND:-make}
python_command=${PYTHON_COMMAND:-python3}
plugin_devel=${RP_PLUGIN_DEVEL:-}
bind_fallback=${RP_BIND920_FALLBACK:-}
compatibility_policy=${RP_BIND_COMPATIBILITY_POLICY:-$repository_root/.resolver-plugins/bind-compatibility.json}
target_pkg_metadata=${RP_TARGET_PKG_METADATA:-$repository_root/.resolver-plugins/target-pkg.json}
pkg_static=${RP_PKG_STATIC_COMMAND:-/usr/local/sbin/pkg-static}

if ! command -v "$python_command" >/dev/null 2>&1
then
    "$pkg_command" install -y python3
fi
command -v "$python_command" >/dev/null 2>&1 || \
    fail 'python3 is not available after package setup'

metadata_field() {
    "$python_command" "$script_directory/metadata_profile.py" \
        "$RP_UPSTREAM_METADATA" "$series" "$1"
}

upstream_commit=$(metadata_field upstream_commit) || fail 'invalid upstream metadata'
core_commit=$(metadata_field core_commit) || fail 'invalid upstream metadata'
tools_tag=$(metadata_field tools_tag) || fail 'invalid upstream metadata'
freebsd_release=$(metadata_field freebsd_release) || fail 'invalid upstream metadata'
minimum_bind_version=$("$python_command" "$script_directory/bind_compatibility.py" \
    minimum-version "$compatibility_policy" "$series") || fail 'invalid BIND compatibility policy'
expected_bind920=$("$python_command" "$script_directory/bind_compatibility.py" \
    identity "$compatibility_policy" "$series" bind920) || fail 'invalid BIND compatibility policy'
expected_bind_tools=$("$python_command" "$script_directory/bind_compatibility.py" \
    identity "$compatibility_policy" "$series" bind_tools) || fail 'invalid BIND compatibility policy'

"$pkg_command" update -f
"$pkg_command" install -y git
git config --global --add safe.directory "$repository_root"
opnsense_core_commit=$("$script_directory/setup-opnsense-repository.sh" "$series")
pkg_creator_record=$("$python_command" "$script_directory/target_pkg.py" install \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static") || fail 'cannot select target package creator'
pkg_creator=$("$python_command" "$script_directory/target_pkg.py" field \
    "$target_pkg_metadata" "$series" version) || fail 'invalid target pkg metadata'
pkg_creator_sha256=$("$python_command" "$script_directory/target_pkg.py" field \
    "$target_pkg_metadata" "$series" sha256) || fail 'invalid target pkg metadata'
[ -n "$pkg_creator_record" ] || fail 'target package creator record is empty'

package_identity() {
    "$pkg_command" query -e "%n = $1" '%n\t%v\t%o'
}

identity_matches_policy() {
    actual=$1
    expected=$2
    actual_name=$(printf '%s\n' "$actual" | cut -f1)
    actual_origin=$(printf '%s\n' "$actual" | cut -f3)
    expected_name=$(printf '%s\n' "$expected" | cut -f1)
    expected_origin=$(printf '%s\n' "$expected" | cut -f2)
    [ "$actual_name" = "$expected_name" ] && [ "$actual_origin" = "$expected_origin" ]
}

if [ "$bind_fallback" = yes ]
then
    bind_source=resolver
else
    if ! "$pkg_command" install -y bind920
    then
        exit 3
    fi
    bind_source=opnsense
fi
bind_identity=$(package_identity bind920) || exit 3
bind_tools_identity=$(package_identity bind-tools) || exit 3
identity_matches_policy "$bind_identity" "$expected_bind920" || exit 3
identity_matches_policy "$bind_tools_identity" "$expected_bind_tools" || exit 3
bind_version=$(printf '%s\n' "$bind_identity" | cut -f2)
comparison=$("$pkg_command" version -t "$bind_version" "$minimum_bind_version") || \
    fail "cannot compare BIND version: $bind_version"
case "$comparison" in
    '='|'>') ;;
    *) exit 3 ;;
esac
opnsense_version=$("$pkg_command" rquery -r OPNsense -e '%n = opnsense' '%v') || \
    fail 'OPNsense core package is not available after package setup'
comparison=$("$pkg_command" version -t "$opnsense_version" 26.1.11_10) || \
    fail "cannot compare OPNsense version: $opnsense_version"
case "$comparison" in
    '='|'>') ;;
    *) fail "OPNsense $opnsense_version is below the required 26.1.11_10" ;;
esac
"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"

make_plugin() {
    if [ "$plugin_devel" = yes ]
    then
        "$make_command" -C "$repository_root/dns/bind" _PLUGIN_DEVEL=yes "$1"
    else
        "$make_command" -C "$repository_root/dns/bind" "$1"
    fi
}

rm -rf "$repository_root/dns/bind/work"
"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"
make_plugin package
"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"

set -- "$repository_root"/dns/bind/work/pkg/os-bind-rp-*.pkg
[ -f "$1" ] || fail 'package build did not produce os-bind-rp'
[ "$#" -eq 1 ] || fail 'package build produced more than one os-bind-rp package'
package=$1
"$python_command" "$script_directory/package_checksums.py" \
    --pkg-command "$pkg_static" "$package"

mkdir -p "$artifact_directory"
cp "$package" "$artifact_directory/"
{
    printf 'series=%s\n' "$series"
    printf 'uname=%s\n' "$(uname -a)"
    printf 'pkg_abi=%s\n' "$("$pkg_command" config ABI)"
    printf 'bind920=%s\n' "$bind_version"
    printf 'bind_source=%s\n' "$bind_source"
    printf 'opnsense=%s\n' "$opnsense_version"
    printf 'opnsense_core_commit=%s\n' "$opnsense_core_commit"
    printf 'upstream_commit=%s\n' "$upstream_commit"
    printf 'core_commit=%s\n' "$core_commit"
    printf 'tools_tag=%s\n' "$tools_tag"
    printf 'freebsd_release=%s\n' "$freebsd_release"
    printf 'source_commit=%s\n' "${SOURCE_COMMIT:-unknown}"
    printf 'pkg_creator=%s\n' "$pkg_creator"
    printf 'pkg_creator_sha256=%s\n' "$pkg_creator_sha256"
} > "$artifact_directory/build-metadata.txt"
