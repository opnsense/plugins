# Getting started

This walks through standing up a small Nebula overlay with the plugin: a
lighthouse and one client, with a CA, certificates, and a firewall rule. It
assumes the `nebula` binaries are installed and the plugin is enabled
(**VPN → Nebula**).

## Concepts in 60 seconds

- **Overlay network** — a private IP range (e.g. `10.10.0.0/24`) that exists
  only inside Nebula. Each node has one overlay IP.
- **Certificate** — a node's identity. It encodes the node's overlay IP and its
  *groups*, and is signed by your **CA**. Nodes only talk to peers whose
  certificate chains to a CA they trust.
- **Lighthouse** — a node with a stable, reachable address that others use to
  find each other. At least one is required; it usually has a public/routable
  address.
- **Instance** — one running Nebula daemon on this firewall, using one
  certificate and one listen port.

## 1. Create a CA (Authorities)

**VPN → Nebula → Authorities → Generate.** Give it a name and (optionally)
networks/groups. The CA's private key stays on the firewall. Every certificate
you sign with it, on any node, will be trusted by nodes that hold this CA.

To use an existing CA, **Import** its certificate (and key, if this box will sign
with it). A CA imported without its key can be *trusted* but not *signed with*.

## 2. Sign host certificates (Host Certificates)

**VPN → Nebula → Host Certificates → Sign.** For each node, choose the CA, set the
node's overlay IP (e.g. `10.10.0.1/24` for the lighthouse, `10.10.0.10/24`
for the client) and any groups (e.g. `lighthouse`, `workstation`). Leave duration
empty to expire just before the CA.

- Sign one certificate **per node** in your overlay. Certificates for other
  firewalls/devices can be downloaded and copied to them.
- **CSR signing** lets a remote node generate its own key and send only a public
  key / CSR; you sign it here and the private key never touches this box. Such a
  certificate is *keyless* on this box and can't be assigned to a local instance.

## 3. Create the lighthouse instance (Instances)

**VPN → Nebula → Instances → Add.**

- **Certificate:** the lighthouse's certificate from step 2.
- **Lighthouse:** enabled.
- **Listen address/port:** `::` and e.g. `4242` (the UDP port peers reach it on;
  make sure it's reachable — see [interfaces.md](interfaces.md) for opening it).
- **Firewall interfaces:** the WAN (or whichever interface) to auto-open the
  listen port on.

Enable the instance and **Apply**. The daemon starts and a `nebula…` tunnel
device comes up with the certificate's overlay IP.

## 4. Create the client instance

On the *client* firewall (or another instance on the same box for testing): add an
instance with the client certificate, **Lighthouse: disabled**, and add the
lighthouse to its **Lighthouse hosts** (the lighthouse's overlay IP) plus a
**static host map** entry pointing that overlay IP at the lighthouse's real
`address:port` (see [routes.md](routes.md)). Enable and Apply.

## 5. Open the overlay firewall

Nebula is **deny-by-default**. On each instance, add at least one **inbound** rule
(VPN → Nebula → Firewall) — e.g. allow `any` from your `workstation` group — or the
node accepts no overlay traffic. See [firewall.md](firewall.md).

## 6. Verify

- **Status** shows each instance running, its certificate, and its listen address.
- **Tunnels** shows live peers once they handshake.
- From a peer, ping the other node's overlay IP.

## Next steps

- [Assign the tunnel as an OPNsense interface](interfaces.md) for firewall
  tabs, gateways, and group rules.
- [Manage certificates and revocation](pki.md) and the [blocklist](blocklist.md).
- [Route non-Nebula subnets over the overlay](routes.md).
