# Routing

**VPN → Nebula → Routes** holds three per-instance route tables: the lighthouse
**static host map**, **unsafe routes**, and **MTU routes**.

## Static host map

How a node finds a specific peer at a fixed underlay address (most importantly,
how clients find a lighthouse). Each entry maps a **Nebula overlay IP** to one or
more real **`address:port`** endpoints.

Typical use: on a client, map the lighthouse's overlay IP (e.g. `10.10.0.1`) to
its public `host:port` (e.g. `vpn.example.org:4242`), and list that overlay IP
under the client instance's **Lighthouse hosts**. The client then reaches the
lighthouse directly and discovers everyone else through it.

## Unsafe routes

Route traffic for a **non-Nebula subnet** over the overlay — e.g. reach a LAN
behind another Nebula node without that LAN running Nebula itself.

- **Route** — the destination subnet (e.g. `192.0.2.0/24`).
- **Via** — the Nebula overlay IP of the node that fronts that subnet.
- **Install** — also add the route to the firewall's system routing table.
- Optional MTU/metric.

The fronting node must be configured to forward for that subnet (and its own
firewall/NAT must allow it).

## MTU routes

Override the MTU for specific overlay routes when a path needs a smaller packet
size (e.g. to avoid fragmentation over a constrained link). Set the **route** and
its **MTU**.

## Notes

- All three are per-instance; select the instance at the top of each table.
- Changes apply on **Apply** (in place, no tunnel drop).
- Static host maps are mainly for lighthouses and other fixed peers; ordinary
  peer-to-peer discovery is handled by the lighthouse automatically.
