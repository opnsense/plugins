#!/bin/sh

set -eu

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

[ "$#" -eq 2 ] || fail "usage: $0 <26.1|26.7> <artifact-directory>"

series=$1
artifact_directory=$2
[ -n "${RP_UPSTREAM_METADATA:-}" ] || fail 'RP_UPSTREAM_METADATA is required'

script_directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repository_root=$(CDPATH= cd -- "$script_directory/../.." && pwd)
profile_path=${RP_BIND920_METADATA:-$repository_root/.resolver-plugins/bind920.json}
pkg_command=${PKG_COMMAND:-pkg}
make_command=${MAKE_COMMAND:-make}
git_command=${GIT_COMMAND:-git}
python_command=${PYTHON_COMMAND:-python3}
target_pkg_metadata=${RP_TARGET_PKG_METADATA:-$repository_root/.resolver-plugins/target-pkg.json}
pkg_static=${RP_PKG_STATIC_COMMAND:-/usr/local/sbin/pkg-static}

metadata_field() {
    "$python_command" "$script_directory/bind920_profile.py" "$profile_path" "$1"
}

ports_repository=$(metadata_field ports_repository) || fail 'invalid BIND profile'
ports_commit=$(metadata_field ports_commit) || fail 'invalid BIND profile'
makefile_sha256=$(metadata_field makefile_sha256) || fail 'invalid BIND profile'
distinfo_sha256=$(metadata_field distinfo_sha256) || fail 'invalid BIND profile'
distversion=$(metadata_field distversion) || fail 'invalid BIND profile'
package_version=$(metadata_field package_version) || fail 'invalid BIND profile'
freebsd_release=$("$python_command" "$script_directory/metadata_profile.py" \
    "$RP_UPSTREAM_METADATA" "$series" freebsd_release) || fail 'invalid upstream metadata'

"$pkg_command" update -f
"$pkg_command" install -y git patch
"$script_directory/setup-opnsense-repository.sh" "$series" >/dev/null
pkg_creator_record=$("$python_command" "$script_directory/target_pkg.py" install \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static") || fail 'cannot select target package creator'
[ -n "$pkg_creator_record" ] || fail 'target package creator record is empty'
"$pkg_command" install -y autoconf automake fstrm gmake json-c libedit libidn2 \
    libnghttp2 libtool liburcu libuv libxml2 lmdb pkgconf protobuf-c
"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"

if [ -n "${RP_BIND920_CHANNEL_URL:-}" ]
then
    if "$python_command" "$script_directory/reuse_bind920.py" \
        "$profile_path" "$series" "$freebsd_release" "$artifact_directory" \
        --channel-url "$RP_BIND920_CHANNEL_URL" \
        --public-key "$repository_root/docs/package-repository/resolver-plugins.pub" \
        --pkg-command "$pkg_command" \
        --pkg-static-command "$pkg_static" \
        --target-pkg-metadata "$target_pkg_metadata"
    then
        exit 0
    else
        reuse_status=$?
    fi
    case "$reuse_status" in
        3) printf '%s\n' 'BIND reuse cache miss; building pinned BIND packages' >&2 ;;
        *) exit "$reuse_status" ;;
    esac
fi

temporary_directory=$(mktemp -d)
ports_directory="$temporary_directory/ports"
trap 'rm -rf "$temporary_directory"' EXIT HUP INT TERM

"$git_command" init "$ports_directory" >/dev/null
"$git_command" -C "$ports_directory" remote add origin "$ports_repository"
"$git_command" -C "$ports_directory" fetch --depth=1 --filter=blob:none origin "$ports_commit"
"$git_command" -C "$ports_directory" checkout --detach FETCH_HEAD >/dev/null
resolved_commit=$("$git_command" -C "$ports_directory" rev-parse HEAD)
[ "$resolved_commit" = "$ports_commit" ] || fail 'FreeBSD Ports checkout does not match the pinned commit'

makefile="$ports_directory/dns/bind920/Makefile"
distinfo="$ports_directory/dns/bind920/distinfo"
[ "$(sha256 -q "$makefile")" = "$makefile_sha256" ] || fail 'BIND Makefile hash does not match pinned metadata'
[ "$(sha256 -q "$distinfo")" = "$distinfo_sha256" ] || fail 'BIND distinfo hash does not match pinned metadata'
grep -Fqx "DISTVERSION=	$distversion" "$makefile" || fail 'BIND Makefile does not declare the pinned version'
patch -d "$ports_directory" -p1 < "$script_directory/patches/bind920-portrevision.patch"

"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"
ALLOW_UNSUPPORTED_SYSTEM=yes BATCH=yes NO_DEPENDS=yes OPTIONS_SET=GSSAPI_NONE OPTIONS_UNSET='DOCS GSSAPI_BASE' "$make_command" -C "$ports_directory/dns/bind-tools" PORTSDIR="$ports_directory" package
"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"
set -- "$ports_directory"/dns/bind-tools/work/pkg/bind-tools-"$package_version".pkg
[ "$#" -eq 1 ] && [ -f "$1" ] || fail 'bind-tools package was not produced as expected'
bind_tools_package=$1
"$python_command" "$script_directory/package_checksums.py" \
    --pkg-command "$pkg_static" "$bind_tools_package"
"$pkg_command" add "$bind_tools_package"

"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"
ALLOW_UNSUPPORTED_SYSTEM=yes BATCH=yes NO_DEPENDS=yes OPTIONS_SET=GSSAPI_NONE OPTIONS_UNSET='DOCS GSSAPI_BASE' "$make_command" -C "$ports_directory/dns/bind920" PORTSDIR="$ports_directory" package
"$python_command" "$script_directory/target_pkg.py" verify \
    "$target_pkg_metadata" "$series" --pkg-command "$pkg_command" \
    --pkg-static "$pkg_static"
set -- "$ports_directory"/dns/bind920/work/pkg/bind920-"$package_version".pkg
[ "$#" -eq 1 ] && [ -f "$1" ] || fail 'bind920 package was not produced as expected'
bind_package=$1
"$python_command" "$script_directory/package_checksums.py" \
    --pkg-command "$pkg_static" "$bind_package"
"$pkg_command" add "$bind_package"

bind_version=$("$pkg_command" query -e '%n = bind920' '%v') || fail 'bind920 is not installed after package setup'
comparison=$("$pkg_command" version -t "$bind_version" "$distversion") || fail 'cannot compare BIND versions'
case "$comparison" in
    '='|'>') ;;
    *) fail "BIND $bind_version is below the required $distversion" ;;
esac

mkdir -p "$artifact_directory"
provenance="$temporary_directory/bind920-provenance.json"
"$python_command" "$script_directory/bind920_profile.py" "$profile_path" \
    provenance "$series" "$freebsd_release" \
    --package-creator "$pkg_creator_record" \
    --bind-tools "$bind_tools_package" --bind920 "$bind_package" --output "$provenance"
cp "$bind_tools_package" "$bind_package" "$provenance" "$artifact_directory/"
