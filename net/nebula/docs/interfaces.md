# Interface assignment & opening the listen port

A Nebula instance creates a tunnel device (`nebula…`). There are two separate
firewall concerns, often confused:

1. **Reaching the node** — peers must be able to send UDP to the instance's
   **listen port** on a *physical* interface (e.g. WAN). This is ordinary OPNsense
   firewalling on the underlay.
2. **Using the overlay as an interface** — optionally assign the `nebula…` device
   as an OPNsense interface so you can route and firewall the overlay traffic.

## Opening the listen port (underlay)

Each instance's **Firewall interfaces** setting auto-creates an inbound rule
allowing UDP to its listen port on the interface(s) you pick (defaults to WAN).
Clear it to write the rule yourself. Without an open port, peers initiating to
this node can't reach it (though it can still reach out to others).

## Assigning the tunnel as an OPNsense interface (overlay)

This makes the Nebula tunnel a first-class interface, like a WireGuard one.

1. **Interfaces → Assignments.** In the device dropdown you'll see entries like
   `nebula3f9a2c (Nebula - <description>)`. Add one; it becomes e.g. *OPT1*.
2. Open the new interface, **Enable** it, and (optionally) rename it (e.g.
   `NEBULA`). You do **not** set an IP — Nebula assigns the overlay address from
   the certificate; OPNsense just tracks the interface.
3. **Apply.** You now have:
   - A **firewall tab** for that interface (Firewall → Rules → NEBULA) to control
     traffic to/from the overlay at the OPNsense layer.
   - A **gateway** you can define for routing.
   - The interface in dashboards, Insight, etc.

### The "Nebula (Group)"

All Nebula tunnels join a `nebula` interface group. When adding a firewall rule,
selecting **"Nebula (Group)"** as the Interface applies that rule to *every*
Nebula tunnel at once — handy when you run several instances. (This is a
packet-filter group, distinct from user-created Interface Groups under
Firewall → Groups.)

## Behaviour notes

- The tunnel device is created by the Nebula daemon and is **volatile** — OPNsense
  knows this, so an assigned-but-not-yet-up interface won't block boot. After a
  reboot the device is recreated and the assignment reattaches automatically.
- Disabling the instance removes the device; the assignment is left in place but
  inactive until you re-enable the instance (or remove the assignment).
- The device name never changes on its own, so assignments and the firewall rules
  bound to them stay correct.

## OPNsense firewall vs Nebula firewall

Assigning the interface lets you filter overlay traffic with **OPNsense** rules.
That is separate from the **Nebula overlay firewall** (VPN → Nebula → Firewall),
which is Nebula's own per-instance policy and is always in effect regardless of
assignment. See [firewall.md](firewall.md).
