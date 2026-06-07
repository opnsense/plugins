# Monitoring: Status & Tunnels

## Status

**VPN → Nebula → Status** is the per-instance health summary:

- **Running / stopped** and the process id.
- **Config valid** — whether the rendered config passes Nebula's own check.
- **Interface** — the tunnel device and its overlay address.
- **Listen** — the bound address and port (IPv6 shown bracketed, e.g.
  `[::]:4242`).
- **Certificate** — the assigned certificate and its expiry (watch this for
  upcoming rotations).

Service **start / restart / stop** controls are in the page header. Most changes
only need **Apply**; use restart only when you intend a full restart.

## Tunnels

**VPN → Nebula → Tunnels** shows live peers across all instances in one list:

- One row per peer, with the instance it belongs to, the peer's overlay
  address(es), its **groups**, and its **known remotes** (the underlay addresses
  it's reachable at).
- **Handshaking** peers (still negotiating) appear too.
- An **instance filter** and search narrow the list; **Refresh** re-queries on
  demand (there's no constant polling, so a busy lighthouse with many peers stays
  responsive).

### Per-peer actions

- **Query lighthouse** — ask the lighthouses where a peer is.
- **Change remote** — point a tunnel at a specific underlay address.
- **Connect / Close** — establish or tear down a tunnel to a peer.

These act on the live daemon and are useful for troubleshooting connectivity.

## Troubleshooting flow

1. **Status:** is the instance running and the config valid? Is the certificate
   current?
2. **Listen port reachable?** Confirm the [underlay rule](interfaces.md) opens the
   UDP port; for clients, confirm the [static host map](routes.md) and lighthouse
   hosts.
3. **Tunnels:** does the peer appear and handshake? If it's stuck handshaking,
   it's usually reachability (port/NAT) or trust (CA/blocklist).
4. **Overlay firewall:** even with a tunnel up, traffic needs an allowing
   [inbound rule](firewall.md) on the receiving node.
