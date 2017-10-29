#!/bin/sh

# Set defaults
POSTFIX_DIRS="/var/spool/postfix /var/db/postfix"
POSTFIX_USER=postfix
POSTFIX_GROUP=wheel

for DIR in ${POSTFIX_DIRS}; do
	mkdir -p ${DIR}
	chmod -R 700 ${DIR}
	chown -R ${POSTFIX_USER}:${POSTFIX_GROUP} ${DIR}
done

# Some folders need special attention
mkdir -p /var/spool/postfix/maildrop
mkdir -p /var/spool/postfix/public
mkdir -p /var/spool/postfix/pid
chmod -R 730 /var/spool/postfix/maildrop
chmod -R 710 /var/spool/postfix/public
chmod -R 755 /var/spool/postfix/pid
chown -R postfix:maildrop /var/spool/postfix/maildrop
chown -R postfix:maildrop /var/spool/postfix/public
chown -R root:postfix /var/spool/postfix/pid

# Create Transporttable
postmap /usr/local/etc/postfix/transport
