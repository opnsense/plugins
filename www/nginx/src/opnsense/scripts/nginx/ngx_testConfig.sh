#!/bin/sh

# run nginx config test. return error text if any. always exit with 0
if conf_test_errors=$(nginx -t -q 2>&1); then
    echo "config is ok"
else
    echo "$conf_test_errors"
fi

exit 0
