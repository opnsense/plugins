#!/bin/sh

/usr/local/bin/cscli --error hub update \
    && /usr/local/bin/cscli --error hub upgrade

if [ ! -e "/usr/local/etc/crowdsec/collections/opnsense.yaml" ]; then
    /usr/local/bin/cscli --error collections install crowdsecurity/opnsense
fi

if service crowdsec enabled; then
    if ! service crowdsec status >/dev/null 2>&1; then
        service crowdsec start >/dev/null 2>&1 || :
    else
        service crowdsec reload >/dev/null 2>&1 || :
    fi
fi
