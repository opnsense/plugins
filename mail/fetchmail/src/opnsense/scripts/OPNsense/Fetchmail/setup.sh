#!/bin/sh

mkdir -p /var/run/fetchmail
chown fetchmail:wheel /var/run/fetchmail
chmod 755 /var/run/fetchmail
chmod 700 /usr/local/etc/fetchmailrc
