Resolver Plugins fork
=====================

This repository is a Resolver Plugins fork of the OPNsense plugins collection.
It currently maintains `os-bind-rp`, a community-maintained BIND plugin based
on the upstream `os-bind` plugin with a small set of additional features.

`os-bind-rp` is intentionally separate from OPNsense's official `os-bind`
package. They conflict and must not be installed together. The current package
build requires OPNsense `26.1.11_10` or newer, which includes the BIND fix
needed for DNS-over-TLS operation.

Packages are published through signed GitHub Release channels. Maintainers
should start with the [maintainer documentation](docs/README.md).

Custom BIND functionality
==========================

`os-bind-rp` keeps the upstream BIND plugin as its base and adds focused DNS
management features:

* DNS-over-TLS (DoT) forwarders with TLS hostname verification, per-forwarder
  destination ports, and Forward First / Forward Only behavior.
* A DHCP lease watcher that publishes scoped dynamic DNS mappings.
* Reverse DNS zone management and optional zone notifications.
* DNSBL definitions sourced from the same lists used by Unbound.
* Listener-interface selection that follows the Unbound model, custom
  `named.conf.d` includes, and forward-zone support.
* HTTPS, SVCB, and NAPTR record support in the BIND record editor.

These additions are implemented for both supported OPNsense release series:
26.1 and 26.7.

Installing os-bind-rp
=====================

`os-bind-rp` has package channels for the OPNsense 26.1 and 26.7 release
series. Select the channel that matches the first two components of the
installed OPNsense version. Do not install it alongside the official `os-bind`
plugin: the two packages conflict by design. The normal channel is
self-contained and includes the `bind920`/`bind-tools` pair used to build the
current plugin.

From an OPNsense root shell, set the supported `major.minor` release series and
configure its current channel:

```sh
series=26.1
url="https://github.com/resolver-plugins/repository/releases/download/pkg-$series"
key=/usr/local/etc/pkg/keys/resolver-plugins.pub
install -d -m 0755 "${key%/*}" /usr/local/etc/pkg/repos
fetch -o "$key" "$url/resolver-plugins.pub"
cat > /usr/local/etc/pkg/repos/resolver-plugins.conf <<EOF
resolver-plugins: { url: "$url", signature_type: "pubkey", pubkey: "$key", enabled: yes }
EOF
pkg update -r resolver-plugins && pkg install os-bind-rp
```

Or install the signed repository and package end-to-end with the interactive
installer:

```sh
fetch -o - https://raw.githubusercontent.com/resolver-plugins/plugins/master/scripts/install-os-bind-rp.sh | sh
```

The installer does not enable the BIND plugin or service, change its
configuration, reload templates, or restart BIND. It checks the installed
OPNsense BIND packages first. If they are incompatible, it explains the
DNS-over-TLS issue, shows the installed and channel versions, and asks before
installing the channel's BIND packages. Declining the prompt leaves
`os-bind-rp` uninstalled.

The repository catalogue and package are signed by the public key above. A
future release for the same OPNsense series updates the same `pkg-<series>`
channel, so normal `pkg upgrade` operations can receive it. The signed
`pkg-<series>-os-bind-rp-<version>` snapshots retain the five newest plugin
rollback versions as self-contained channels in the same distribution
repository. See the [package repository guide](docs/package-repository.md)
before changing a release channel or signing key.

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
devel/grid_example -- A sample framework application
devel/helloworld -- A sample framework application
dns/bind -- BIND domain name service
dns/ddclient -- Dynamic DNS client
dns/dnscrypt-proxy -- Flexible DNS proxy supporting DNSCrypt and DoH
dns/rfc2136 -- RFC-2136 Support
emulators/qemu-guest-agent -- QEMU Guest Agent for OPNsense
ftp/tftp -- TFTP server
mail/postfix -- SMTP mail relay
mail/rspamd -- Protect your network from spam
misc/theme-advanced -- Theme based on AdvancedTomato GUI
misc/theme-cicada -- The cicada theme - dark grey onyx
misc/theme-flexcolor -- Theme with 3 different color schemes: black as default, light and dark-light
misc/theme-rebellion -- A suitably dark theme
misc/theme-tukan -- The tukan theme - blue/white
misc/theme-vicuna -- The vicuna theme - blue sapphire
net/chrony -- Chrony time synchronisation
net/cloudflared -- Cloudflare Tunnel integration
net/firewall-legacy -- Firewall rules legacy support
net/freeradius -- RADIUS Authentication, Authorization and Accounting Server
net/frr -- The FRRouting Protocol Suite
net/ftp-proxy -- Control ftp-proxy processes
net/google-cloud-sdk -- Google Cloud SDK
net/haproxy -- Reliable, high performance TCP/HTTP load balancer
net/igmp-proxy -- IGMP-Proxy Service (not maintained)
net/isc-dhcp -- ISC DHCPv4/v6 server
net/mdns-repeater -- Proxy multicast DNS between networks
net/ndp-proxy-go -- IPv6 Neighbor Discovery Protocol (NDP) Proxy
net/ntopng -- Traffic Analysis and Flow Collection
net/radsecproxy -- RADIUS proxy provides both RADIUS UDP and TCP/TLS (RadSec) transport
net/realtek-re -- Realtek re(4) vendor driver
net/relayd -- Relayd Load Balancer
net/shadowsocks -- Secure socks5 proxy
net/siproxd -- Siproxd is a proxy daemon for the SIP protocol
net/sslh -- sslh configuration front-end
net/tayga -- Tayga NAT64
net/turnserver -- The coturn STUN/TURN Server
net/udpbroadcastrelay -- Control udpbroadcastrelay processes
net/upnp -- UPnP IGD & PCP/NAT-PMP Service
net/vnstat -- Network traffic monitor
net/wol -- Wake on LAN Service (not maintained)
net/zerotier -- Virtual Networks That Just Work (not maintained)
net-mgmt/collectd -- Collect system and application performance metrics periodically
net-mgmt/lldpd -- LLDP allows you to know exactly on which port is a server
net-mgmt/net-snmp -- Net-SNMP is a daemon for the SNMP protocol
net-mgmt/netdata -- Real-time performance monitoring
net-mgmt/nrpe -- Execute nagios plugins
net-mgmt/telegraf -- Agent for collecting metrics and data
net-mgmt/zabbix-agent -- Zabbix monitoring agent
net-mgmt/zabbix-proxy -- Zabbix monitoring proxy
security/acme-client -- ACME Client
security/clamav -- Antivirus engine for detecting malicious threats
security/crowdsec -- Lightweight and collaborative security engine
security/etpro-telemetry -- ET Pro Telemetry Edition
security/intrusion-detection-content-at-antiphishing -- Anti-phishing rules
security/intrusion-detection-content-et-open -- IDS Proofpoint full ET open ruleset complementary subset for ET Pro Telemetry edition
security/intrusion-detection-content-et-pro -- IDS Proofpoint ET Pro ruleset (needs a valid subscription)
security/intrusion-detection-content-pt-open -- IDS Positive Technologies ESC ruleset
security/intrusion-detection-content-snort-vrt -- IDS Snort VRT ruleset (needs registration or subscription)
security/maltrail -- Malicious traffic detection system
security/netbird -- Peer-to-peer VPN that seamlessly connects your devices
security/openconnect -- OpenConnect Client
security/openvpn-legacy -- OpenVPN legacy support
security/q-feeds-connector -- Connector for Q-Feeds threat intel
security/strongswan-legacy -- IPsec legacy support
security/stunnel -- Stunnel TLS proxy
security/tailscale -- VPN mesh securely connecting clients using WireGuard
security/tinc -- Tinc VPN
security/tor -- The Onion Router
security/wazuh-agent -- Agent for the open source security platform Wazuh
sysutils/apcupsd -- APCUPSD - APC UPS daemon
sysutils/beats -- Send logs, network, metrics and heartbeat to Elasticsearch
sysutils/cpu-microcode -- CPU microcode updates
sysutils/dec-hw -- Deciso hardware specific information
sysutils/dmidecode -- Display hardware information on the dashboard (not maintained)
sysutils/gdrive-backup -- Backup configurations using Google Drive
sysutils/git-backup -- Track config changes using git
sysutils/hw-probe -- Collect hardware diagnostics
sysutils/lcdproc-sdeclcd -- LCDProc for SDEC LCD devices (not maintained)
sysutils/mail-backup -- Send configuration file backup by e-mail
sysutils/munin-node -- Munin monitoring agent
sysutils/nextcloud-backup -- Track config changes using NextCloud (not maintained)
sysutils/node_exporter -- Prometheus exporter for machine metrics
sysutils/nut -- Network UPS Tools
sysutils/puppet-agent -- Manage Puppet Agent
sysutils/sftp-backup -- Backup configurations using SFTP
sysutils/smart -- SMART tools (not maintained)
sysutils/virtualbox -- VirtualBox guest additions
sysutils/vmware -- VMware tools
sysutils/xen -- Xen guest utilities
vendor/sunnyvalley -- Vendor Repository for Zenarmor - Enterprise SASE & SSE platform (NGFW, SWG, CASB, ZTNA, SD-WAN)
www/OPNProxy -- OPNsense proxy additions (not maintained)
www/c-icap -- c-icap connects the web proxy with a virus scanner
www/cache -- Webserver cache
www/caddy -- Modern Reverse Proxy with Automatic HTTPS, Dynamic DNS and Layer4 Routing
www/nginx -- Nginx HTTP server and reverse proxy
www/squid -- Squid is a caching proxy for the web (not maintained)
www/web-proxy-sso -- Kerberos authentication module (not maintained)
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
* style:	run style checks
* sweep:	apply style fixes

The make targets for any plugin directory:

* clean:	remove all changes and unknown files
* collect:	gather updates from target directory
* install:	install to target directory
* lint:		run syntax checks
* package:	creates a package
* upgrade:	upgrades existing package
* remove:	remove known files from target directory
* style:	run style checks
* sweep:	apply style fixes
