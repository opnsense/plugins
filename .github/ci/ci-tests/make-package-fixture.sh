#!/bin/sh

set -eu

[ "$1" = "-C" ] || exit 2
case "$2" in
    */dns/bind) ;;
    *) exit 2 ;;
esac
plugin_directory=$2

case "$3" in
    clean)
        for package in "$plugin_directory"/work/pkg/os-bind-rp-*.pkg
        do
            [ -f "$package" ] || continue
            rm -f "$package"
        done
        exit 0
        ;;
    package) ;;
    *) exit 2 ;;
esac

fixture_directory=$(mktemp -d)
trap 'rm -rf "$fixture_directory"' EXIT
mkdir -p "$plugin_directory/work/pkg"
printf '%s\n' \
    'name: os-bind-rp' \
    'version: "1.36_3"' \
    'deps: {' \
    '  bind920: { version: "9.20.24", origin: "dns/bind920" }' \
    '  opnsense: { version: "26.1.11_10", origin: "opnsense/opnsense" }' \
    '}' > "$fixture_directory/+MANIFEST"
tar -C "$fixture_directory" -cf "$plugin_directory/work/pkg/os-bind-rp-1.36_3.pkg" +MANIFEST
