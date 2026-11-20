#!/bin/sh

# the encryption secret is readable by root only
/usr/bin/install -d -m 0700 /usr/local/etc/checkmkagent
if [ -f /usr/local/etc/checkmkagent/secret ]; then
	/bin/chmod 0600 /usr/local/etc/checkmkagent/secret
fi
