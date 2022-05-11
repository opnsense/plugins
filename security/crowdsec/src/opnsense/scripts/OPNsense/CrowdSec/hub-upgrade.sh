#!/bin/sh

if [ ! -e "/usr/local/etc/crowdsec/collections/opnsense.yaml" ]; then
    /usr/local/bin/cscli --error collections install crowdsecurity/opnsense
fi

/usr/local/bin/cscli --error hub update \
    && /usr/local/bin/cscli --error hub upgrade

if service crowdsec enabled; then
    # have to check status explicitly because "restart" can set $? = 0 even when failing
    if ! service crowdsec status >/dev/null 2>&1; then
        service crowdsec start >/dev/null 2>&1 || :
    else
        service crowdsec restart >/dev/null 2>&1 || :
    fi
fi

