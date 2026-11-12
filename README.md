# Sunset

I'm sunsetting this:

Seems there was not a lot of interest. Even some pushback. Further, I've made the switch to VyOs.

PRs are welcome, or someone can fork it. Otherwise I will soon archive this.

# Resolver Plugins for OPNsense

Community-maintained OPNsense plugins, based on the upstream OPNsense
plugins collection. Published packages share one signed repository.

## Maintained plugins

| Plugin | Package | Purpose |
|---|---|---|
| BIND | `os-bind-rp` | BIND DNS management with DNS-over-TLS, dynamic DHCP mappings, reverse DNS and DNSBL integration. |
| HA DHCP Interface | `os-ha-dhcp-interface` | Experimental CARP-aware high availability for an IPv4 DHCP client interface. |

### BIND with DNS-over-TLS, dynamic DHCP mappings and DNSBLs

Requires OPNsense `26.7` or newer.
Replaces the official `os-bind` package; the two packages cannot be
installed together.

BIND comes from OPNsense's own packages; `os-bind-rp` declares the minimum
BIND version it needs.

`os-bind-rp` keeps the upstream BIND plugin as its base and adds focused DNS
management features:

* DNS-over-TLS (DoT) forwarders with TLS hostname verification, per-forwarder
  destination ports, and Forward First / Forward Only behavior.
* A DHCP lease watcher that publishes scoped dynamic DNS mappings.
* Reverse DNS zone management and optional zone notifications.
* DNSBL definitions sourced from the same lists used by Unbound.
* Listener-interface selection that follows the Unbound model, custom
  `named.conf.d` includes, and forward-zone support.

### HA DHCP Interface

Experimental plugin for OPNsense 26.7 on amd64.
The package joins the shared feed after its first successful publication.

Supports a shared MAC with node-local interface assignments in an existing
active/passive CARP pair. OPNsense continues to manage DHCP, addressing,
routing and firewall services.

See the [HA DHCP Interface tutorial](docs/plugins/ha-dhcp-interface/tutorial.md)
for requirements and setup, and its
[explanation](docs/plugins/ha-dhcp-interface/explanation.md) for current limitations.

## Configure the package repository

From an OPNsense root shell, configure the signed repository for your installed
OPNsense series and ABI. This setup is shared by both plugins and only needs to
be done once. Existing Resolver Plugins users can refresh the catalogue with
`pkg update -r resolver-plugins` and proceed to package installation.

### Option 1: One-line setup

```sh
curl -fsSL https://raw.githubusercontent.com/resolver-plugins/plugins/master/scripts/install-repository.sh | sh
```

### Option 2: Manual setup

```sh
series="$(opnsense-version -a)"
repo_url="https://resolver-plugins.github.io/repository/pkg/\${ABI}/$series/latest"
fetch_url="https://resolver-plugins.github.io/repository/pkg/$(pkg config ABI)/$series/latest"
key=/usr/local/etc/pkg/keys/resolver-plugins.pub
install -d -m 0755 "${key%/*}" /usr/local/etc/pkg/repos
fetch -o "$key" "$fetch_url/resolver-plugins.pub"
test "$(sha256 -q "$key")" = \
  bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e || exit 1
cat > /usr/local/etc/pkg/repos/resolver-plugins.conf <<EOF
resolver-plugins: {
  url: "$repo_url",
  mirror_type: "none",
  signature_type: "pubkey",
  pubkey: "$key",
  enabled: yes
}
EOF
pkg update -r resolver-plugins
```

## Install a plugin

### BIND

```sh
pkg install os-bind-rp
```

`pkg` takes BIND from any enabled repository that meets the plugin's minimum
version, normally OPNsense's own, and replaces an installed official `os-bind`.

### HA DHCP Interface

```sh
pkg install os-ha-dhcp-interface
```

## Documentation

- [Plugin documentation](docs/plugins/README.md): tutorials, how-to guides,
  reference and explanation for both plugins.
- [Maintainer documentation](docs/README.md): the package repository,
  releases, series upgrades, the CI and upstream synchronization.

## Upstream

This repository is a fork of the [OPNsense plugins collection](https://github.com/opnsense/plugins).
The broader plugin tree and build framework come from OPNsense and its contributors.
The packages maintained by Resolver Plugins are listed above; see the upstream
project for its full plugin catalogue and development documentation.
