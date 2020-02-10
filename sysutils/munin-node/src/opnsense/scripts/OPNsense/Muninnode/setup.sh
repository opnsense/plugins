#!/bin/sh

mkdir -p /var/munin/plugin-state/
mkdir -p /var/log/munin/
mkdir -p /var/run/munin/
chown -R munin:munin /var/munin/plugin-state/ /var/munin/ /var/log/munin/ /var/run/munin/
chmod 755 /var/munin/plugin-state/ /var/munin/ /var/log/munin/ /var/run/munin/
