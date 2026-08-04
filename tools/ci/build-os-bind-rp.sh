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

"$pkg_command" update -f
"$pkg_command" install -y git
git config --global --add safe.directory "$repository_root"
opnsense_core_commit=$("$script_directory/setup-opnsense-repository.sh" "$series")
"$pkg_command" install -y bind920
bind_version=$("$pkg_command" query -e '%n = bind920' '%v') || \
    fail 'bind920 is not installed after package setup'
opnsense_version=$("$pkg_command" rquery -r OPNsense -e '%n = opnsense' '%v') || \
    fail 'OPNsense core package is not available after package setup'
comparison=$("$pkg_command" version -t "$opnsense_version" 26.1.11_10) || \
    fail "cannot compare OPNsense version: $opnsense_version"
case "$comparison" in
    '='|'>') ;;
    *) fail "OPNsense $opnsense_version is below the required 26.1.11_10" ;;
esac

make_plugin() {
    if [ "$plugin_devel" = yes ]
    then
        "$make_command" -C "$repository_root/dns/bind" _PLUGIN_DEVEL=yes "$1"
    else
        "$make_command" -C "$repository_root/dns/bind" "$1"
    fi
}

make_plugin clean
make_plugin package

set -- "$repository_root"/dns/bind/work/pkg/os-bind-rp-*.pkg
[ -f "$1" ] || fail 'package build did not produce os-bind-rp'
[ "$#" -eq 1 ] || fail 'package build produced more than one os-bind-rp package'
package=$1

mkdir -p "$artifact_directory"
cp "$package" "$artifact_directory/"
{
    printf 'series=%s\n' "$series"
    printf 'uname=%s\n' "$(uname -a)"
    printf 'pkg_abi=%s\n' "$("$pkg_command" config ABI)"
    printf 'bind920=%s\n' "$bind_version"
    printf 'opnsense=%s\n' "$opnsense_version"
    printf 'opnsense_core_commit=%s\n' "$opnsense_core_commit"
    printf 'upstream_commit=%s\n' "$upstream_commit"
    printf 'core_commit=%s\n' "$core_commit"
    printf 'tools_tag=%s\n' "$tools_tag"
    printf 'freebsd_release=%s\n' "$freebsd_release"
    printf 'source_commit=%s\n' "${SOURCE_COMMIT:-unknown}"
} > "$artifact_directory/build-metadata.txt"
