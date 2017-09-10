#!/bin/sh
mkdir -p /var/db/tor
mkdir -p /var/log/tor
mkdir -p /var/run/tor

if [ ! -f "/var/db/tor/hashed_control_password" ]; then
PASSWORD=$( /usr/local/bin/openssl rand -base64 32 )
echo -n "HashedControlPassword " > /var/db/tor/hashed_control_password
tor --quiet --hash-password "$PASSWORD" >> /var/db/tor/hashed_control_password
echo "$PASSWORD" > /var/db/tor/control_password
fi

