#!/bin/sh

for CONF in /usr/local/etc/ddclient.conf /usr/local/etc/ddclient.json; do
	chmod 0600 ${CONF}
done

/usr/local/opnsense/scripts/ddclient/generate_perl_wrappers.py
