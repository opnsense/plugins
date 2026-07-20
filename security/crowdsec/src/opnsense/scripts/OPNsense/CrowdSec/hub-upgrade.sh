#!/bin/sh

test -x /usr/local/bin/cscli || exit 0

/usr/local/bin/cscli --error -o human hub update >/dev/null

_setup=$(mktemp /tmp/crowdsec-setup.XXXXXX.yaml)
/usr/local/bin/cscli setup detect --detect-config /usr/local/etc/crowdsec/detect.yaml --outfile "${_setup}"
/usr/local/bin/cscli setup install-hub --file "${_setup}"
rm -f "${_setup}"

upgraded=$(/usr/local/bin/cscli --error -o human hub upgrade)

if [ ! -e "/usr/local/etc/crowdsec/collections/opnsense.yaml" ]; then
    /usr/local/bin/cscli --error collections install crowdsecurity/opnsense
fi

if service crowdsec enabled; then
    if ! service crowdsec status >/dev/null 2>&1; then
        service crowdsec start >/dev/null 2>&1 || :
    else
        if [ -n "$upgraded" ]; then
            service crowdsec reload >/dev/null 2>&1 || :
        fi
    fi
fi
