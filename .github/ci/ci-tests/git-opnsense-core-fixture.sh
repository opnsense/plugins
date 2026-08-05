#!/bin/sh

set -eu

if [ "$1" = "clone" ]; then
    for destination
    do
        :
    done
    mkdir -p "$destination/src/etc/pkg/repos"
    mkdir -p "$destination/src/etc/pkg/fingerprints/OPNsense/trusted"
    printf '%s\n' \
        'OPNsense: {' \
        '  url: "https://pkg.opnsense.org/${ABI}/26.1/latest"' \
        '  signature_type: "fingerprints"' \
        '  fingerprints: "/usr/local/etc/pkg/fingerprints/OPNsense"' \
        '  enabled: yes' \
        '}' > "$destination/src/etc/pkg/repos/OPNsense.conf"
    printf '%s\n' \
        'function: "sha256"' \
        'fingerprint: "fixture"' > \
        "$destination/src/etc/pkg/fingerprints/OPNsense/trusted/pkg.opnsense.org.fixture"
    exit 0
fi

if [ "$1" = "-C" ] && [ "$3" = "rev-parse" ] && [ "$4" = "HEAD" ]; then
    printf '%s\n' 'fixture-opnsense-core-commit'
    exit 0
fi

exit 2
