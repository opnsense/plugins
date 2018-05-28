About the OPNsense plugins
==========================

The plugins collection offers users and developers a way to quickly
build additions for OPNsense that can be optionally installed.  As
soon as they are upstreamed they will become available to everyone
through the firmware GUI pages.

Plugins can do the following:

* Modify the menu, access control lists and look and feel (themes)
* Add additional server software and their respective GUI pages
* Create new authentication methods to be used within other subsystems
* Provide other types of devices and interfaces to the firewall
* Pull in additional packages that will update automatically
* Enhance the backend services with additional work tasks
* Allow custom start, stop and early scripts
* Persistent /boot/loader.conf modifications
* Additional themes for the web GUI

Now we need your help to enrich the plugins.  Feel free to contact us
at project AT opnsense DOT org or open GitHub issue to get in touch.


Stay safe,
Your OPNsense team

A list of currently available plugins
=====================================

```
benchmarks/iperf -- Connection speed tester
databases/redis -- Redis DB
devel/debug -- Debugging Tools
devel/helloworld -- A sample framework application
dns/dyndns -- Dynamic DNS Support
dns/rfc2136 -- RFC-2136 Support
mail/postfix -- SMTP mail relay
mail/rspamd -- Protect your network from spam
misc/theme-cicada -- The cicada theme - grey/orange
misc/theme-rebellion -- A suitably dark theme
misc/theme-tukan -- The tukan theme - blue/white
net-mgmt/collectd -- Collect system and application performance metrics periodically
net-mgmt/lldpd -- LLDP allows you to know exactly on which port is a server
net-mgmt/net-snmp -- Net-SNMP is a daemon for the SNMP protocol
net-mgmt/snmp -- SNMP Server via bsnmpd
net-mgmt/telegraf -- Agent for collecting metrics and data
net-mgmt/zabbix-agent -- Enterprise-class open source distributed monitoring agent
net-mgmt/zabbix-proxy -- Zabbix Proxy enables decentralized monitoring
net/arp-scan -- Get all peers connected to a local network
net/freeradius -- RADIUS Authentication, Authorization and Accounting Server
net/frr -- The FRRouting Protocol Suite
net/ftp-proxy -- Control ftp-proxy processes
net/haproxy -- Reliable, high performance TCP/HTTP load balancer
net/igmp-proxy -- IGMP-Proxy Service
net/l2tp -- L2TP server based on MPD5
net/mdns-repeater -- Proxy multicast DNS between networks
net/pppoe -- PPPoE server based on MPD5
net/pptp -- PPTP server based on MPD5
net/quagga -- End of life, superseded by FRR plugin
net/relayd -- Relayd Load Balancer
net/shadowsocks -- Secure socks5 proxy
net/siproxd -- Siproxd is a proxy daemon for the SIP protocol
net/upnp -- Universal Plug and Play Service
net/wol -- Wake on LAN Service
net/zerotier -- Virtual Networks That Just Work
security/acme-client -- Let's Encrypt client
security/clamav -- Antivirus engine for detecting malicious threats
security/intrusion-detection-content-et-pro -- IDS Proofpoint ET Pro ruleset (needs a valid subscription)
security/intrusion-detection-content-pt-open -- IDS PT Research ruleset (only for non-commercial use)
security/intrusion-detection-content-snort-vrt -- IDS Snort VRT ruleset (needs registration or subscription)
security/openconnect -- OpenConnect Client
security/tinc -- Tinc VPN
security/tor -- The Onion Router
sysutils/boot-delay -- Apply a persistent 10 second boot delay
sysutils/lcdproc-sdeclcd -- LCDProc for SDEC LCD devices
sysutils/monit -- Proactive system monitoring
sysutils/node_exporter -- Prometheus exporter for machine metrics
sysutils/nut -- Network UPS Tools
sysutils/smart -- SMART tools
sysutils/vmware -- VMware tools
sysutils/xen -- Xen guest utilities
www/c-icap -- c-icap connects the web proxy with a virus scanner
www/cache -- Webserver cache
www/web-proxy-sso -- Kerberos authentication module
www/web-proxy-useracl -- Group and user ACL for the web proxy
```

A brief description of how to use the plugins repository
========================================================

The workflow of the plugins repository is quite similar to the
core repository, although the plugins have one source directory
per plugin, while the core can be thought of a lone plugin.

Commits for individual plugins should therefore be split into
individual chunks for each src/ directory so that they can be
reviewed separately and also be applied remotely.

When an OPNsense release is built, the plugins are automatically
added to the final package repository.

The most useful Makefile targets and their purpose is described
below.

The make targets for the root directory:

* clean:	remove all changes and unknown files
* lint:		run syntax checks
* list:		print a list of all plugin directories with comments
* style-fix:	apply style fixes
* style:	run style checks
* sweep:	apply whitespace fixes

The make targets for any plugin directory:

* clean:	remove all changes and unknown files
* collect:	gather updates from target directory
* install:	install to target directory
* lint:		run syntax checks
* package:	creates a package
* upgrade:	upgrades existing package
* remove:	remove known files from target directory
* style-fix:	apply style fixes
* style:	run style checks
* sweep:	apply whitespace fixes
