#!/bin/sh
# Copyright (c) 2026 Emanuele Baglini
# SPDX-License-Identifier: BSD-2-Clause
set -eu

test_dir=$(CDPATH= cd "$(dirname "$0")" && pwd -P)
plugin_dir=$(dirname "$test_dir")
core_commit=cdd7a38717b67ce90b673bd528ddaf2b2b42d8d7
image=php@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3
core_dir=
offline=no

usage() {
    echo "Usage: $0 [--core /path/to/core] [--offline]"
    echo "See $test_dir/README.md for dependencies and isolation details."
}
fail() { echo "$*" >&2; exit 1; }
while [ "$#" -gt 0 ]; do
    case "$1" in
        --core)
            [ "$#" -ge 2 ] || fail "--core needs a directory."
            core_dir=$2
            shift 2
            ;;
        --offline) offline=yes; shift ;;
        --help|-h) usage; exit 0 ;;
        *) usage >&2; exit 1 ;;
    esac
done

command -v docker >/dev/null 2>&1 || fail "Docker is required."
if [ "$offline" = yes ] && [ -z "$core_dir" ]; then
    fail "--offline requires --core and a cached PHP image."
fi
if command -v sha256sum >/dev/null 2>&1; then
    sha256_file() { digest=$(sha256sum "$1"); echo "${digest%% *}"; }
elif command -v shasum >/dev/null 2>&1; then
    sha256_file() { digest=$(shasum -a 256 "$1"); echo "${digest%% *}"; }
elif command -v sha256 >/dev/null 2>&1; then
    sha256_file() { sha256 -q "$1"; }
else
    fail "sha256sum, shasum or sha256 is required."
fi

scratch=$(mktemp -d "${TMPDIR:-/tmp}/tailscale-tests.XXXXXX")
trap 'rm -rf "$scratch"' 0
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

download=no
if [ -z "$core_dir" ]; then
    command -v curl >/dev/null 2>&1 || fail "curl is required to prepare core references."
    core_dir=$scratch/core
    mkdir "$core_dir"
    download=yes
else
    [ -d "$core_dir" ] || fail "Core directory not found: $core_dir"
    core_dir=$(CDPATH= cd "$core_dir" && pwd -P)
fi

echo "Verifying opnsense/core $core_commit"
while read -r expected path; do
    case "$expected" in ''|\#*) continue ;; esac
    if [ "$download" = yes ]; then
        mkdir -p "$(dirname "$core_dir/$path")"
        curl --fail --location --silent --show-error --connect-timeout 15 --max-time 120 \
            "https://raw.githubusercontent.com/opnsense/core/$core_commit/$path" \
            -o "$core_dir/$path"
    fi
    [ -f "$core_dir/$path" ] || fail "Missing core reference: $path"
    [ "$(sha256_file "$core_dir/$path")" = "$expected" ] || fail "Core checksum mismatch: $path"
done < "$test_dir/core.sha256"

if ! docker image inspect "$image" >/dev/null 2>&1; then
    [ "$offline" = no ] || fail "The pinned PHP image is not cached (or Docker is unavailable)."
    docker pull "$image"
fi

echo "Running isolated tests with $image"
# Only include names matter here; scenario.php supplies their simulated behaviour.
mkdir "$scratch/includes"
for name in auth config empty filter interfaces system util; do
    printf '<?php\n' > "$scratch/includes/$name.inc"
done
docker run --rm --pull never --network none --read-only \
    --cap-drop ALL --security-opt no-new-privileges --pids-limit 128 \
    --user "$(id -u):$(id -g)" \
    --tmpfs /tmp:rw,exec,nosuid,nodev,size=64m \
    --mount "type=bind,source=$plugin_dir,target=/plugin,readonly" \
    --mount "type=bind,source=$core_dir,target=/core,readonly" \
    --mount "type=bind,source=$scratch/includes,target=/test-includes,readonly" \
    --env TAILSCALE_TEST_SANDBOX=1 --env TAILSCALE_TEST_CORE=/core \
    --workdir /plugin "$image" \
    php -d display_errors=stderr -d error_reporting=-1 tests/run.php
