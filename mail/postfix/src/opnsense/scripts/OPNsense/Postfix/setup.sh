#!/bin/sh

mkdir -p /var/spool/postfix/
chown root:wheel /var/spool/postfix
chmod 755 /var/spool/postfix

# Set defaults
POSTFIX_DIRS="/var/spool/postfix/active /var/spool/postfix/bounce /var/spool/postfix/corrupt /var/spool/postfix/defer /var/spool/postfix/deferred /var/spool/postfix/flush /var/spool/postfix/hold /var/spool/postfix/incoming /var/spool/postfix/private /var/spool/postfix/saved /var/spool/postfix/trace /var/db/postfix"
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
postmap /usr/local/etc/postfix/recipient_access
postmap /usr/local/etc/postfix/sender_access

# Check for aliases
if [ -f /usr/local/etc/postfix/aliases ]; then
       echo "Updating aliases"
       /usr/local/bin/newaliases
else
       echo "Adding aliases"
       touch /usr/local/etc/postfix/aliases
       /usr/local/bin/newaliases
fi


/usr/local/opnsense/scripts/OPNsense/Postfix/generate_certs.php
