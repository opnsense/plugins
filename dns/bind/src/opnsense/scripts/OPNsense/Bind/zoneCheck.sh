#!/bin/sh

# check the primary zone file validity

ZONENAME=${1}
ZONEPATH="/usr/local/etc/namedb/primary/${ZONENAME}.db"
if checkzone_errors=$(named-checkzone ${ZONENAME} ${ZONEPATH} 2>&1); then
    echo "Zone check completed successfully"
    echo "$checkzone_errors"
else
    echo "$checkzone_errors"
fi

exit 0
