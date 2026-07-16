#!/bin/sh

# Check the effective primary zone. Dynamic updates are held in a companion
# .jnl file, so named-checkzone must load it as well when it is present.

ZONENAME="$1"
if [ "$#" -ne 1 ] || [ -z "$ZONENAME" ]; then
    echo "usage: zoneCheck.sh <zone-name>"
    exit 1
fi

# Zone names originate in the model, but keep this configd entry point safe
# when it is used directly as well.
case "$ZONENAME" in
    *[!A-Za-z0-9.-]* | .*)
        echo "invalid zone name: $ZONENAME"
        exit 1
        ;;
esac

ZONEPATH="/usr/local/etc/namedb/primary/${ZONENAME}.db"
if [ -f "${ZONEPATH}.jnl" ]; then
    checkzone_label="including dynamic update journal"
    checkzone_errors=$(named-checkzone -j "$ZONENAME" "$ZONEPATH" 2>&1)
    checkzone_status=$?
else
    checkzone_label=""
    checkzone_errors=$(named-checkzone "$ZONENAME" "$ZONEPATH" 2>&1)
    checkzone_status=$?
fi

if [ "$checkzone_status" -eq 0 ]; then
    echo "Zone check completed successfully"
    [ -n "$checkzone_label" ] && echo "Validated $checkzone_label."
    echo "$checkzone_errors"
else
    echo "$checkzone_errors"
fi

exit 0
