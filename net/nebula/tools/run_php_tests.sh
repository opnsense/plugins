#!/bin/sh
#
# run_php_tests.sh — run the Nebula model test suite against an opnsense/core checkout.
#
# The plugin ships only its own classes + tests; the OPNsense MVC model tests need
# the core framework (OPNsense\Base / OPNsense\Core), which lives in opnsense/core.
# This script clones core (shallow) if needed, overlays the plugin's app + tests
# into it, points the test config's writable dirs at a local scratch dir, and runs
# phpunit from core's test harness.
#
# NOTE ON PHALCON: tests that exercise BaseModel validation (~13 of them) need the
# Phalcon PHP C-extension. On a PHP build without Phalcon they error with
#   Error: Class "Phalcon\Filter\Validation" not found
# and the remaining tests (incl. GenerateConfigTest) still run. On an OPNsense
# appliance — or any PHP with `pecl install phalcon` — the full suite runs.
#
# Usage:
#   tools/run_php_tests.sh [extra phpunit args]   # e.g. --testdox, or a single Test.php
# Env:
#   CORE_DIR   where to find/clone opnsense/core   (default: ~/src/opnsense-core)
#
set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PLG_MVC="$(cd "$SCRIPT_DIR/.." && pwd)/src/opnsense/mvc"
CORE_DIR="${CORE_DIR:-$HOME/src/opnsense-core}"

if [ ! -d "$CORE_DIR/.git" ]; then
    echo ">> cloning opnsense/core (shallow) into $CORE_DIR"
    git clone --depth 1 https://github.com/opnsense/core.git "$CORE_DIR"
fi

CORE_MVC="$CORE_DIR/src/opnsense/mvc"

# Overlay plugin classes + tests into the core tree (the autoloader scans the
# controllers/models dirs; phpunit discovers *Test.php under tests/app).
echo ">> overlaying plugin into $CORE_DIR"
rm -rf "$CORE_MVC/app/controllers/OPNsense/Nebula" \
       "$CORE_MVC/app/models/OPNsense/Nebula" \
       "$CORE_MVC/tests/app/models/OPNsense/Nebula"
cp -R "$PLG_MVC/app/controllers/OPNsense/Nebula" "$CORE_MVC/app/controllers/OPNsense/"
cp -R "$PLG_MVC/app/models/OPNsense/Nebula"      "$CORE_MVC/app/models/OPNsense/"
mkdir -p "$CORE_MVC/tests/app/models/OPNsense"
cp -R "$PLG_MVC/tests/app/models/OPNsense/Nebula" "$CORE_MVC/tests/app/models/OPNsense/"

# Writable scratch for config/cache/temp. The suite overrides configDir to its own
# per-test fixture, but cache/temp must still be writable. Rewrite the default
# /var/lib/php/tests paths in the (scratch) core test config once.
SCRATCH="$CORE_DIR/.testtmp"
mkdir -p "$SCRATCH"
CFG="$CORE_MVC/tests/app/config/config.php"
if grep -q "/var/lib/php/tests" "$CFG"; then
    perl -pi -e "s#/var/lib/php/tests#$SCRATCH#g" "$CFG"
fi

echo ">> running phpunit"
cd "$CORE_MVC/tests"
exec phpunit --bootstrap setup.php --do-not-cache-result app/models/OPNsense/Nebula "$@"
