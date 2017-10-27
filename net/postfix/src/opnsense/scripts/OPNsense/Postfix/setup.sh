#!/bin/sh

POSTFIX_DIRS="/var/spool/postfix /var/db/postfix"
POSTFIX_USER=postfix
POSTFIX_GROUP=postfix

for DIR in ${POSTFIX_DIRS}; do
	mkdir -p ${DIR}
	chmod -R 750 ${DIR}
	chown -R ${POSTFIX_USER}:${POSTFIX_GROUP} ${DIR}
done


