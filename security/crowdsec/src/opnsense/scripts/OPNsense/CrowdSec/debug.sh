#!/bin/sh

printf "service oscrowdsec enabled: "
if service oscrowdsec enabled; then echo "YES"; else echo "NO"; fi
echo

printf "service crowdsec enabled: "
if service oscrowdsec enabled; then echo "YES"; else echo "NO"; fi
printf "service crowdsec status: "; service crowdsec status
echo

echo "crowdsec version:"
crowdsec -version 2>&1
echo

printf "service crowdsec_firewall enabled: "
if service crowdsec_firewall enabled; then echo "YES"; else echo "NO"; fi
printf "service crowdsec_firewall status: "; service crowdsec_firewall status
echo

echo "crowdsec-firewall-bouncer version:"
crowdsec-firewall-bouncer -V
echo

printf "pf anchor: "
if ! pfctl -sa | grep crowdsec; then "NO"; fi

