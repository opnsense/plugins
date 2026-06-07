# Overlay firewall

**VPN → Nebula → Firewall** controls Nebula's *own* firewall — the policy applied
to traffic **inside** the overlay, per instance. This is separate from the
OPNsense firewall (which you can also apply if you [assign the tunnel as an
interface](interfaces.md)).

## Deny-by-default

Nebula passes **nothing** until you allow it. A node with no inbound rules accepts
no overlay traffic. So every instance needs at least one **inbound** rule to be
reachable. (Outbound defaults are more permissive, but you can lock those down
too.)

## Rules

Each rule is **inbound** or **outbound** and matches on:

- **Port / protocol** — e.g. allow `tcp/22`, or `any`.
- **Host / CIDR** — a peer overlay IP or subnet (`local_cidr` matches the local
  side).
- **Groups** — match peers whose certificate carries a group (e.g. `workstation`,
  `servers`). Group-based rules are the idiomatic way to write policy: tag nodes
  with groups when you sign their certificates, then allow by group.
- **CA name / CA fingerprint** — restrict to peers signed by a specific CA.

Rules are per-instance and take effect on **Apply** (in place, no tunnel drop).

## Examples

- *Allow management from admins:* inbound, `tcp/22`, group `admins`.
- *Allow all traffic between workstations:* inbound, `any`, group `workstation`.
- *Lighthouse that only relays discovery:* keep inbound minimal; the lighthouse
  doesn't need to accept general traffic.

## Tips

- Prefer **groups** over hard-coded IPs — they survive renumbering and scale.
- Keep one explicit inbound rule even on a lighthouse, or it accepts nothing.
- If you also assigned the interface, remember traffic must pass **both** the
  Nebula firewall and the OPNsense interface rules.
