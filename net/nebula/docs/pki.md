# Certificates & PKI

Nebula identity is certificate-based. A private **Certificate Authority (CA)**
signs a **host certificate** for each node; nodes trust peers whose certificate
chains to a CA they hold. This plugin manages both, plus revocation via the
[blocklist](blocklist.md).

## Authorities (CAs)

**VPN → Nebula → Authorities.**

- **Generate** — create a new CA on this firewall. Set a name, curve (25519 is the
  default; P256 for hardware/compat), validity, and optional default
  networks/groups. The private key stays on the box.
- **Import** — bring in an existing CA. Import the certificate alone to *trust* a
  CA (verify peers), or the certificate **and** its key to also *sign* with it
  here. A keyless CA shows **Can sign = no**.
- **Encrypted CA** — a CA whose key is passphrase-protected; you'll be prompted
  for the passphrase when signing. Shown with a lock marker.
- The **Fingerprint** column shows the first 8 hex characters of the CA's SHA-256,
  to tell apart CAs with similar names.
- **Purge expired** removes CAs past their validity, but skips any still in use
  (referenced by a certificate or trusted by an instance) and tells you which.

## Host Certificates

**VPN → Nebula → Host Certificates.**

- **Sign** — issue a certificate under one of your CAs. Set the node's overlay IP
  (e.g. `10.10.0.10/24`), groups, and validity (leave empty to expire just before
  the CA). The plugin generates the key + certificate on the box; download them to
  copy onto another node, or assign the certificate to a local instance.
- **CSR signing** — sign from a public key or CSR generated **on the remote
  node**, so that node's private key never leaves it. The resulting certificate is
  *keyless* here: it can't be assigned to a local instance (an instance needs the
  key to run), but it's perfect for issuing identities to other machines.
- **Import** — add a pre-signed certificate (with or without its key).
- Each certificate shows its **fingerprint** (first 8 hex of SHA-256) and the
  signing CA as `name: XXXXXXXX`.
- **Block** (per row) immediately revokes a certificate by adding it to the global
  [blocklist](blocklist.md).
- **Purge expired** removes expired certificates, skipping any still assigned to an
  instance.

## Typical lifecycle

1. Generate (or import) a CA.
2. Sign a certificate per node — locally for this firewall's instances, or via CSR
   for remote nodes.
3. Assign a certificate to an [instance](instances.md).
4. To revoke a node before its certificate expires, [block its
   fingerprint](blocklist.md).
5. Rotate before expiry: sign a replacement, swap it on the instance, Apply.

## Notes

- Keep the CA's validity comfortably longer than the certificates it signs;
  certificates default to expiring just before the CA.
- Nodes must share at least one common CA to talk. Multiple CAs let you segment
  trust; an instance can trust additional CAs beyond the one that signed it.
