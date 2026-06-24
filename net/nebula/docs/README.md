# os-nebula documentation

User and admin documentation for the os-nebula OPNsense plugin. New to it? Start
with the project [README](../README.md), then:

## Using the plugin

- [Getting started](getting-started.md) — stand up a lighthouse + client end to
  end.
- [Certificates & PKI](pki.md) — CAs, host certificates, CSR signing.
- [Instances](instances.md) — configuring a Nebula daemon.
- [Interface assignment](interfaces.md) — opening the listen port, and assigning
  the tunnel as an OPNsense interface.
- [Overlay firewall](firewall.md) — Nebula's per-instance inbound/outbound policy.
- [Blocklist](blocklist.md) — revoking certificates.
- [Routing](routes.md) — static host maps, unsafe routes, MTU.
- [Monitoring](status-tunnels.md) — the Status and Tunnels pages.

## Contributing

- [Development guide](development.md) — architecture, build, test, conventions.
