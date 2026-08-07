#!/bin/sh

set -eu

readonly minimum_bind920_version=9.20.26
readonly public_key_sha256=bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e
readonly release_base=https://github.com/resolver-plugins/repository/releases/download

state_directory=
temporary_directory=
temporary_directory_owned=no
pkg_lock_changed=no
completed=no
key_stage_directory=
key_stage_owned=no
transaction_started=no
recovery_repository=
recovery_repository_directory=
recovery_exact_identities=

cleanup() {
    status=$?
    trap - EXIT HUP INT TERM
    if [ "$pkg_lock_changed" = yes ]
    then
        "$pkg_command" lock -u -y pkg >/dev/null 2>&1 || \
            printf '%s\n' 'WARNING: could not restore the original pkg lock state' >&2
    fi
    if [ "$status" -eq 0 ] && [ "$completed" = yes ] && \
        [ "$temporary_directory_owned" = yes ] && [ -n "$temporary_directory" ] && \
        [ "$temporary_directory" != / ]
    then
        rm -rf "$temporary_directory"
    elif [ "$status" -ne 0 ]
    then
        if [ -n "$state_directory" ]
        then
            printf 'Diagnostic state retained at %s\n' "$state_directory" >&2
        fi
        if [ -n "$temporary_directory" ]
        then
            printf 'Temporary package data retained at %s\n' "$temporary_directory" >&2
        fi
        if [ "$transaction_started" = yes ] && [ -n "$recovery_repository" ]
        then
            printf 'Recovery package repository: %s\n' "$recovery_repository" >&2
            printf '%s\n' 'Dry-run recovery before applying it:' >&2
            printf '  %s -o REPOS_DIR=%s install -n -f -r resolver-recovery%s\n' \
                "$pkg_static_command" "$recovery_repository_directory" \
                "$recovery_exact_identities" >&2
            printf '%s\n' 'Stop BIND before an approved recovery, restore config.xml.bak, then validate and restart BIND.' >&2
        fi
    fi
    if [ "$key_stage_owned" = yes ] && [ -n "$key_stage_directory" ] && \
        [ "$key_stage_directory" != / ]
    then
        rm -rf "$key_stage_directory"
    fi
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM

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
    destination=$4
    key=${5:-}
    {
        printf '%s\n' "$name: {"
        printf '  url: "%s",\n' "$url"
        printf '%s\n' '  mirror_type: "none",'
        if [ -n "$key" ]
        then
            printf '%s\n' '  signature_type: "pubkey",'
            printf '  pubkey: "%s",\n' "$key"
        else
            printf '%s\n' '  signature_type: "none",'
        fi
        printf '  enabled: %s\n' "$enabled"
        printf '%s\n' '}'
    } > "$destination"
}

series_from_opnsense_version() {
    product_version=$(opnsense-version 2>/dev/null | awk 'NR == 1 { print $2 }')
    series=$(printf '%s\n' "$product_version" | sed -E 's/^([0-9]+\.[0-9]+).*/\1/')
    case "$series" in
        26.1)
            case "$("$pkg_command" version -t "$product_version" '26.1.11_10')" in
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

remote_pkg() {
    "$pkg_command" -o "REPOS_DIR=$repository_directory" "$@"
}

verified_pkg() {
    "$pkg_static_command" -o "REPOS_DIR=$isolated_repository_directory" "$@"
}

package_dry_run() {
    output=$1
    repository_config=$2
    repository_name=$3
    force=$4
    shift 4
    set +e
    if [ "$force" = yes ]
    then
        "$pkg_static_command" -o "REPOS_DIR=$repository_config" install -n -f \
            -r "$repository_name" "$@" > "$output" 2>&1
    else
        "$pkg_static_command" -o "REPOS_DIR=$repository_config" install -n \
            -r "$repository_name" "$@" > "$output" 2>&1
    fi
    status=$?
    set -e
    case "$status" in
        0|1) ;;
        *) fail "package dry run failed with status $status" ;;
    esac
    grep -Eq 'will be affected|already installed' "$output" || \
        fail 'package dry run did not produce a recognized transaction plan'
    for identity in "$@"
    do
        grep -Fq "$identity" "$output" || \
            fail "package dry run omitted requested identity: $identity"
    done
    if grep -Eq '(^|[[:space:]])(pkg|opnsense)(-[0-9]|:[[:space:]]*[0-9])' "$output"
    then
        fail 'package dry run attempted to change pkg or OPNsense core'
    fi
}

remote_package() {
    requested_name=$1
    expected_origin=$2
    record=$(remote_pkg rquery -r resolver-plugins -e "%n = $requested_name" '%n|%v|%o') || \
        fail "could not inspect $requested_name in the Resolver repository"
    [ "$(printf '%s\n' "$record" | awk 'NF { count++ } END { print count + 0 }')" -eq 1 ] || \
        fail "Resolver repository returned an ambiguous $requested_name candidate"
    name=$(package_field "$record" 1)
    version=$(package_field "$record" 2)
    origin=$(package_field "$record" 3)
    if [ "$name" != "$requested_name" ] || [ "$origin" != "$expected_origin" ] || \
        ! printf '%s\n' "$version" | grep -Eq '^[A-Za-z0-9][A-Za-z0-9._+,-]*$'
    then
        fail "Resolver repository returned an invalid $requested_name identity"
    fi
    printf '%s\n' "$record"
}

version_at_least() {
    case "$("$pkg_command" version -t "$1" "$2")" in
        '='|'>') return 0 ;;
        *) return 1 ;;
    esac
}

installed_bind_is_eligible() {
    bind920_name=$(package_field "$bind920" 1)
    bind920_version=$(package_field "$bind920" 2)
    bind920_origin=$(package_field "$bind920" 3)
    bind_tools_name=$(package_field "$bind_tools" 1)
    bind_tools_version=$(package_field "$bind_tools" 2)
    bind_tools_origin=$(package_field "$bind_tools" 3)

    [ "$bind920_name" = bind920 ] && [ "$bind920_origin" = dns/bind920 ] || return 1
    [ "$bind_tools_name" = bind-tools ] && [ "$bind_tools_origin" = dns/bind-tools ] || return 1
    version_at_least "$bind920_version" "$minimum_bind920_version" || return 1
    version_at_least "$bind920_version" "$candidate_bind920_version" || return 1
    version_at_least "$bind_tools_version" "$candidate_bind_tools_version" || return 1
}

reject_downgrade() {
    installed_record=$1
    candidate_version=$2
    package_name=$3
    installed_version=$(package_field "$installed_record" 2)
    if [ -n "$installed_version" ] && \
        [ "$("$pkg_command" version -t "$installed_version" "$candidate_version")" = '>' ]
    then
        fail "refusing to downgrade $package_name from $installed_version to $candidate_version"
    fi
}

create_state_directory() {
    umask 077
    if [ -n "${RP_STATE_DIRECTORY:-}" ]
    then
        state_directory=$RP_STATE_DIRECTORY
    else
        backup_root=${RP_BACKUP_ROOT:-/var/backups}
        mkdir -p "$backup_root"
        state_directory="$backup_root/os-bind-rp-install.$(date -u +%Y%m%dT%H%M%SZ).$$"
    fi
    mkdir -m 0700 "$state_directory" || fail "could not create state directory: $state_directory"
    config_file=${RP_CONFIG_FILE:-/conf/config.xml}
    [ -f "$config_file" ] || fail "OPNsense configuration is missing: $config_file"
    cp -p "$config_file" "$state_directory/config.xml.bak"
    "$pkg_command" info -a > "$state_directory/packages.before.txt"
    "$pkg_command" query '%n|%v|%o' | sort > "$state_directory/package-identities.before.txt"
    cp "$state_directory/package-identities.before.txt" "$state_directory/installed-packages.txt"
    "$pkg_command" lock -l > "$state_directory/package-locks.before.txt"
}

prepare_temporary_directory() {
    if [ -n "${RP_TEMPORARY_DIRECTORY:-}" ]
    then
        temporary_directory=$RP_TEMPORARY_DIRECTORY
        mkdir -p "$temporary_directory"
    else
        temporary_directory=$(mktemp -d /tmp/os-bind-rp-install.XXXXXX)
        temporary_directory_owned=yes
    fi
    chmod 0700 "$temporary_directory"
    archive_root="$temporary_directory/verified-repository"
    archive_all="$archive_root/All"
    isolated_repository_directory="$temporary_directory/isolated-repos"
    mkdir -p "$archive_all" "$isolated_repository_directory"
    : > "$temporary_directory/package-file-checksums.txt"
    : > "$temporary_directory/package-archives.txt"
}

archive_path_for() {
    identity=$1
    for candidate in \
        "$archive_root/$identity.pkg" "$archive_root/$identity.txz" \
        "$archive_all/$identity.pkg" "$archive_all/$identity.txz"
    do
        if [ -f "$candidate" ]
        then
            printf '%s\n' "$candidate"
        fi
    done
}

verify_archive_manifest() {
    package_name=$1
    package_version=$2
    package_origin=$3
    archive=$4
    checksum_output=$5
    identity="$package_name-$package_version"
    archive_identity=$("$pkg_static_command" query -F "$archive" '%n|%v|%o') || \
        fail "could not inspect archive identity for $identity"
    [ "$archive_identity" = "$package_name|$package_version|$package_origin" ] || \
        fail "archive identity mismatch for $identity: $archive_identity"

    "$pkg_static_command" query -F "$archive" '%Fp|%Fs' > "$checksum_output" || \
        fail "could not inspect file checksums for $identity"
    [ -s "$checksum_output" ] || fail "archive contains no file checksums: $identity"
    while IFS='|' read -r file_path file_checksum extra
    do
        if [ -z "$file_path" ] || [ -n "${extra:-}" ] || \
            ! printf '%s\n' "$file_checksum" | grep -Eq '^([12]\$)?[[:xdigit:]]{64}$'
        then
            fail "incompatible file checksum in $identity for ${file_path:-unknown}: ${file_checksum:-(null)}"
        fi
    done < "$checksum_output"
}

fetch_and_verify_archive() {
    package_name=$1
    package_version=$2
    package_origin=$3
    identity="$package_name-$package_version"
    remote_pkg fetch -y -r resolver-plugins -o "$archive_root" "$identity" || \
        fail "could not fetch exact package $identity"
    archive=$(archive_path_for "$identity")
    [ "$(printf '%s\n' "$archive" | awk 'NF { count++ } END { print count + 0 }')" -eq 1 ] || \
        fail "expected one archive for $identity"
    checksum_output="$temporary_directory/$package_name.file-checksums"
    verify_archive_manifest "$package_name" "$package_version" "$package_origin" \
        "$archive" "$checksum_output"
    while IFS='|' read -r file_path file_checksum
    do
        printf '%s|%s|%s|%s\n' "$identity" "$file_path" "$file_checksum" "$archive" \
            >> "$temporary_directory/package-file-checksums.txt"
    done < "$checksum_output"
    archive_sha256=$(sha256 -q "$archive")
    printf '%s|%s|%s\n' "$identity" "$archive_sha256" "$archive" \
        >> "$temporary_directory/package-archives.txt"
}

verify_archive_hashes() {
    while IFS='|' read -r identity expected archive
    do
        [ "$(sha256 -q "$archive")" = "$expected" ] || \
            fail "verified archive changed before installation: $identity"
    done < "$temporary_directory/package-archives.txt"
}

recovery_archive_path() {
    identity=$1
    for candidate in \
        "$recovery_repository/$identity.pkg" "$recovery_repository/$identity.txz" \
        "$recovery_repository/All/$identity.pkg" "$recovery_repository/All/$identity.txz"
    do
        if [ -f "$candidate" ]
        then
            printf '%s\n' "$candidate"
        fi
    done
}

capture_recovery_packages() {
    recovery_repository="$state_directory/recovery-packages"
    recovery_repository_directory="$state_directory/recovery-repos"
    mkdir -m 0700 "$recovery_repository" "$recovery_repository_directory"
    : > "$state_directory/recovery-identities.txt"
    recovery_exact_identities=
    set --
    for record in "$bind_tools" "$bind920" "$official_plugin" "$installed_plugin"
    do
        package_name=$(package_field "$record" 1)
        if [ -n "$package_name" ]
        then
            set -- "$@" "$package_name"
            printf '%s\n' "$record" >> "$state_directory/recovery-identities.txt"
        fi
    done
    [ "$#" -gt 0 ] || return 0
    "$pkg_command" create -o "$recovery_repository" "$@" || \
        fail 'could not preserve installed packages for recovery'
    set --
    while IFS='|' read -r package_name package_version package_origin
    do
        identity="$package_name-$package_version"
        archive=$(recovery_archive_path "$identity")
        [ "$(printf '%s\n' "$archive" | awk 'NF { count++ } END { print count + 0 }')" -eq 1 ] || \
            fail "expected one recovery archive for $identity"
        verify_archive_manifest "$package_name" "$package_version" "$package_origin" \
            "$archive" "$state_directory/$package_name.recovery-checksums.txt"
        set -- "$@" "$identity"
        recovery_exact_identities="$recovery_exact_identities $identity"
    done < "$state_directory/recovery-identities.txt"
    "$pkg_static_command" repo "$recovery_repository" || \
        fail 'could not construct recovery package repository'
    write_repository resolver-recovery "file://$recovery_repository" yes \
        "$recovery_repository_directory/resolver-recovery.conf"
    package_dry_run "$state_directory/pkg-recovery.dry-run.txt" \
        "$recovery_repository_directory" resolver-recovery yes "$@"
}

installed_record() {
    "$pkg_command" query -e "%n = $1" '%n|%v|%o' 2>/dev/null || true
}

verify_installed_record() {
    expected=$1
    package_name=$(package_field "$expected" 1)
    actual=$(installed_record "$package_name")
    [ "$actual" = "$expected" ] || fail "installed identity mismatch for $package_name: ${actual:-missing}"
}

verify_archive_ownership() {
    while IFS='|' read -r identity file_path file_checksum archive
    do
        package_name=${identity%-*}
        installed_identity=$(installed_record "$package_name")
        installed_version=$(package_field "$installed_identity" 2)
        expected_owner="$package_name-$installed_version"
        owner=$("$pkg_command" which -q "$file_path") || \
            fail "installed file is not package-owned: $file_path ($identity)"
        [ "$owner" = "$expected_owner" ] || \
            fail "installed file has the wrong owner: $file_path ($owner, expected $expected_owner)"
    done < "$temporary_directory/package-file-checksums.txt"
}

verify_installed_checksums() {
    package_name=$1
    checksum_output="$temporary_directory/$package_name.installed-checksums"
    "$pkg_command" query -e "%n = $package_name" '%Fp|%Fs' > "$checksum_output" || \
        fail "could not inspect installed checksums for $package_name"
    [ -s "$checksum_output" ] || fail "installed package has no file checksums: $package_name"
    while IFS='|' read -r file_path file_checksum extra
    do
        if [ -z "$file_path" ] || [ -n "${extra:-}" ] || \
            ! printf '%s\n' "$file_checksum" | grep -Eq '^([12]\$)?[[:xdigit:]]{64}$'
        then
            fail "incompatible installed file checksum in $package_name for ${file_path:-unknown}: ${file_checksum:-(null)}"
        fi
    done < "$checksum_output"
}

pkg_command=${RP_PKG_COMMAND:-pkg}
pkg_static_command=${RP_PKG_STATIC_COMMAND:-/usr/local/sbin/pkg-static}
repository_directory=${RP_PKG_REPOSITORY_DIR:-/usr/local/etc/pkg/repos}
key_directory=${RP_PKG_KEYS_DIR:-/usr/local/etc/pkg/keys}
mkdir -p "$repository_directory" "$key_directory"

series=$(series_from_opnsense_version)
current_channel=pkg-$series
public_key="$key_directory/resolver-plugins.pub"
if [ -n "${RP_TEMPORARY_DIRECTORY:-}" ]
then
    key_stage_directory=$RP_TEMPORARY_DIRECTORY
else
    key_stage_directory=$(mktemp -d /tmp/os-bind-rp-key.XXXXXX)
    key_stage_owned=yes
fi
mkdir -p "$key_stage_directory"
public_key_candidate="$key_stage_directory/resolver-plugins.pub"

fetch -o "$public_key_candidate" "$release_base/$current_channel/resolver-plugins.pub"
[ "$(sha256 -q "$public_key_candidate")" = "$public_key_sha256" ] || \
    fail 'resolver-plugins public-key fingerprint verification failed'
install -m 0644 "$public_key_candidate" "$public_key"
if [ "$key_stage_owned" = yes ]
then
    rm -rf "$key_stage_directory"
    key_stage_directory=
    key_stage_owned=no
fi

write_repository resolver-plugins "$release_base/$current_channel" yes \
    "$repository_directory/resolver-plugins.conf" "$public_key"
remote_pkg update -r resolver-plugins

candidate_bind920=$(remote_package bind920 dns/bind920)
candidate_bind_tools=$(remote_package bind-tools dns/bind-tools)
candidate_plugin=$(remote_package os-bind-rp opnsense/os-bind-rp)
candidate_bind920_version=$(package_field "$candidate_bind920" 2)
candidate_bind_tools_version=$(package_field "$candidate_bind_tools" 2)
candidate_plugin_version=$(package_field "$candidate_plugin" 2)

bind920=$(installed_record bind920)
bind_tools=$(installed_record bind-tools)
bind_update_required=no
if ! installed_bind_is_eligible
then
    bind_update_required=yes
    reject_downgrade "$bind920" "$candidate_bind920_version" bind920
    reject_downgrade "$bind_tools" "$candidate_bind_tools_version" bind-tools
    printf '%s\n' "Installed bind920: $(package_description "$bind920")" >&2
    printf '%s\n' "Installed bind-tools: $(package_description "$bind_tools")" >&2
    printf '%s\n' \
        "Available fallback: bind920 $candidate_bind920_version and bind-tools $candidate_bind_tools_version" >&2
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
        y|Y|yes|YES|Yes) ;;
        *) fail 'BIND update declined; os-bind-rp was not installed.' ;;
    esac
fi

official_plugin=$(installed_record os-bind)
installed_plugin=$(installed_record os-bind-rp)
if [ -n "$official_plugin" ]
then
    printf '%s\n' "Replacing official os-bind ($(package_description "$official_plugin")) with os-bind-rp." >&2
elif [ -n "$installed_plugin" ]
then
    printf '%s\n' "Upgrading installed os-bind-rp ($(package_description "$installed_plugin"))." >&2
fi

prepare_temporary_directory
fetch_and_verify_archive bind920 "$candidate_bind920_version" dns/bind920
fetch_and_verify_archive bind-tools "$candidate_bind_tools_version" dns/bind-tools
fetch_and_verify_archive os-bind-rp "$candidate_plugin_version" opnsense/os-bind-rp

"$pkg_static_command" repo "$archive_root" || fail 'could not construct verified local package repository'
write_repository resolver-verified "file://$archive_root" yes \
    "$isolated_repository_directory/resolver-verified.conf"
opnsense_repository_config=${RP_OPNSENSE_REPOSITORY_CONFIG:-/usr/local/etc/pkg/repos/OPNsense.conf}
[ -f "$opnsense_repository_config" ] || \
    fail "OPNsense repository configuration is missing: $opnsense_repository_config"
cp -p "$opnsense_repository_config" "$isolated_repository_directory/OPNsense.conf"

verify_archive_hashes
create_state_directory
cp "$temporary_directory/package-file-checksums.txt" "$state_directory/package-file-checksums.txt"
cp "$temporary_directory/package-archives.txt" "$state_directory/package-archives.txt"
cp "$temporary_directory/package-archives.txt" "$state_directory/candidate-sha256.txt"
printf '%s\n' "$candidate_bind920" "$candidate_bind_tools" "$candidate_plugin" \
    > "$state_directory/candidates.txt"

pkg_identity_before=$(installed_record pkg)
if "$pkg_command" lock -l | grep -Eq '(^|[[:space:]])pkg-[^[:space:]]*($|[[:space:]])'
then
    pkg_was_locked=yes
else
    pkg_was_locked=no
    "$pkg_command" lock -y pkg >/dev/null
    pkg_lock_changed=yes
fi
[ "$(installed_record pkg)" = "$pkg_identity_before" ] || \
    fail 'pkg identity changed while establishing the transaction lock'
printf 'pkg_was_locked=%s\npkg_identity=%s\n' "$pkg_was_locked" "$pkg_identity_before" \
    > "$state_directory/transaction.txt"

set -- "os-bind-rp-$candidate_plugin_version"
if [ "$bind_update_required" = yes ]
then
    set -- "bind920-$candidate_bind920_version" "bind-tools-$candidate_bind_tools_version" "$@"
fi
package_dry_run "$state_directory/pkg-install.dry-run.txt" \
    "$isolated_repository_directory" resolver-verified no "$@"
verify_archive_hashes
capture_recovery_packages
[ "$(installed_record pkg)" = "$pkg_identity_before" ] || \
    fail 'pkg identity changed before the verified package transaction'

transaction_started=yes
if [ "$bind_update_required" = yes ]
then
    verified_pkg install -y -r resolver-verified \
        "bind920-$candidate_bind920_version" "bind-tools-$candidate_bind_tools_version"
fi
verified_pkg install -y -r resolver-verified "os-bind-rp-$candidate_plugin_version"

[ -z "$(installed_record os-bind)" ] || fail 'official os-bind remains installed after replacement'
verify_installed_record "$candidate_plugin"
if [ "$bind_update_required" = yes ]
then
    verify_installed_record "$candidate_bind920"
    verify_installed_record "$candidate_bind_tools"
fi
verify_archive_ownership
for installed_package in bind-tools bind920 os-bind-rp
do
    verify_installed_checksums "$installed_package"
done
"$pkg_command" check -s bind-tools bind920 os-bind-rp
"$pkg_command" info -a > "$state_directory/packages.after.txt"
"$pkg_command" query '%n|%v|%o' > "$state_directory/package-identities.after.txt"

completed=yes
printf 'Installed %s using verified exact archives. Recovery state: %s\n' \
    "os-bind-rp-$candidate_plugin_version" "$state_directory" >&2
