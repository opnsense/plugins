# Instances

An **instance** is one running Nebula daemon on this firewall. Most deployments
need just one; run several when you want independent overlays (e.g. a lighthouse
plus a separate client overlay) on the same box. **VPN → Nebula → Instances.**

## Key settings

- **Enabled** — master switch for this instance's daemon.
- **Certificate** — the host certificate this node presents (from [Host
  Certificates](pki.md)). Required. Its overlay IP becomes the tunnel's address.
- **Lighthouse** — make this node a discovery server. Lighthouses should not list
  other lighthouses; clients list the lighthouse(s) under **Lighthouse hosts**.
- **Listen address / port** — the UDP socket Nebula binds. `::` listens on all
  interfaces (IPv4+IPv6); the default port is `4242`. Port `0` picks a random port
  (useful for roaming clients that don't accept inbound connections).
- **Lighthouse hosts** — on a client, the overlay IPs of the lighthouse(s) it
  reports to and queries.
- **Firewall interfaces** — which OPNsense interface(s) to automatically open the
  UDP listen port on (so peers can reach this node). Defaults to WAN on a new
  instance; clear it to manage that rule yourself. See [interfaces.md](interfaces.md).

Advanced options (relays, hole-punching, cipher, MTU, handshake tuning, logging,
conntrack) are available but rarely need changing.

## The interface name

Each instance gets a system tunnel device named `nebula…` (e.g. `nebula3f9a2c`).
It's assigned automatically, stable for the life of the instance, and not
editable — much like WireGuard's `wgN`. You'll see it in the Instances grid and in
**Interfaces → Assignments**.

## Applying changes

**Apply** reloads each running instance **in place** — existing tunnels stay up.
Only a change that Nebula can't apply live (the listen address/port or cipher)
restarts that instance, briefly dropping its tunnels. Adding/altering firewall
rules, routes, lighthouse settings, or rotating the certificate all apply without
a tunnel drop.

## Enable / disable & lifecycle

- Disabling an instance stops its daemon and removes its tunnel device (and drops
  it from Interfaces → Assignments).
- Re-enabling and applying recreates the device and reconnects.
- Use the per-row **Restart** only when you want a full restart; normal edits just
  need **Apply**.

## Checklist for a working instance

1. A valid, assigned **certificate** with the key (keyless CSR-signed certs can't
   be assigned).
2. **Lighthouse** set correctly (server vs client), with reachable lighthouse
   hosts on clients.
3. A reachable **listen port** (opened on the right interface).
4. At least one **inbound** [firewall rule](firewall.md) — Nebula is
   deny-by-default.
