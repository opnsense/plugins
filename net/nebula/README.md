# os-nebula

Manage [Nebula](https://github.com/slackhq/nebula) mesh overlay VPNs from the
OPNsense web GUI.

Nebula is a scalable overlay networking tool: hosts authenticate to each other
with certificates issued by a private CA, discover each other through
*lighthouses*, and form encrypted peer-to-peer tunnels on a private overlay
network. This plugin brings the whole workflow — PKI, daemon instances, overlay
firewall, routing, and live monitoring — into OPNsense, and makes each Nebula
tunnel a first-class OPNsense interface.

## Features

- **Multiple instances** — run several independent Nebula daemons on one box
  (e.g. a lighthouse and a separate client overlay), each with its own
  certificate, listen port, and overlay firewall.
- **Built-in PKI** — create or import Certificate Authorities, sign host
  certificates (including CSR signing where the private key never leaves the
  requesting device), and revoke by fingerprint with a blocklist.
- **First-class interfaces** — assign a Nebula tunnel in *Interfaces →
  Assignments* like a WireGuard interface; write firewall rules and gateways
  against it, or against the whole `nebula` group.
- **Overlay firewall** — Nebula's own inbound/outbound policy per instance.
- **Routing** — lighthouse static host maps, routes for non-Nebula subnets over
  the overlay, and per-route MTU overrides.
- **Live monitoring** — a Tunnels page showing peers, groups and known remotes
  across all instances, and a per-instance Status page.

## Requirements

- OPNsense (recent release).
- The `nebula` and `nebula-cert` binaries installed on the firewall (from the
  FreeBSD `security/nebula` port / the OPNsense package set).

## Install

This plugin lives under `net/nebula` in an `opnsense/plugins`-style tree. Build
and install it the standard OPNsense plugin way (`make install` from the plugin
directory on a build host, or via your plugin repository once packaged). After
installation, enable it under **VPN → Nebula**.

## Quickstart

1. **Authorities** → create a CA.
2. **Host Certificates** → sign a certificate under that CA (set the Nebula
   overlay IP/networks and any groups).
3. **Instances** → add an instance, select the certificate, set whether it's a
   lighthouse and its listen port, and enable it.
4. **Apply.** The daemon starts and the tunnel device comes up.
5. (Optional) **Interfaces → Assignments** → assign the Nebula device to give it
   a firewall tab and gateway.

See [`docs/getting-started.md`](docs/getting-started.md) for the full walkthrough.

## Documentation

- **Using the plugin:** [`docs/`](docs/) — getting started, PKI, instances,
  interface assignment, overlay firewall, blocklist, routes, monitoring.
- **Contributing / development:** [`docs/development.md`](docs/development.md).
- **Known issues & limitations:** [`BUGS.md`](BUGS.md).

(AI coding assistants additionally pick up `AGENTS.md` and `.claude/` at the repo
root — the same knowledge written for tools.)

## License

BSD 2-Clause (see source file headers).
