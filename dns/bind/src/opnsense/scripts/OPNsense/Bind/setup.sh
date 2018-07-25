#!/bin/sh

mkdir -p /var/run/named
chown -R bind:bind /var/run/named
chmod 755 /var/run/named

mkdir -p /var/dump
chown -R bind:bind /var/dump
chmod 755 /var/dump

mkdir -p /var/stats
chown -R bind:bind /var/stats
chmod 755 /var/stats

mkdir -p /var/log/named
chown -R bind:bind /var/log/named
chmod 755 /var/log/named
