#!/bin/sh
mkdir -p /var/db/rspamd
mkdir -p /var/log/rspamd
mkdir -p /var/run/rspamd

chown nobody:nobody /var/db/rspamd
chown nobody:nobody /var/log/rspamd
chown nobody:nobody /var/run/rspamd
