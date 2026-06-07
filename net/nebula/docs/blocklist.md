# Blocklist (revocation)

Nebula certificates can't be un-signed, so to revoke a node before its certificate
expires you **block its fingerprint**. A blocked certificate is refused by the
instances that carry the block. **VPN → Nebula → Blocklist.**

## Adding a block

- **Add** — paste the certificate's 64-character SHA-256 fingerprint, or pick a
  certificate you hold (which fills the fingerprint for you). Choose a **scope**:
  - **Global** — every instance refuses it.
  - **Instance** — only the selected instance refuses it.
- **Block** from the [Host Certificates](pki.md) page — one click adds a global
  block for that certificate.
- **Import** — paste many fingerprints at once (one per line, optionally
  `fingerprint, description`).

The **Certificate** column resolves a fingerprint to a certificate you hold
(`name: XXXXXXXX`), or shows `unknown: XXXXXXXX` for a foreign one.

## Expiry & purging

An entry may carry an optional **Expiry** date, but note: **a block stays in
effect until you remove it.** Expiry is only a marker for the **Purge expired**
button, which deletes past-expiry entries on demand. This keeps revocations from
silently lapsing — you decide when to clean them up. (A natural time to purge is
once the blocked certificate would itself have expired.)

## Notes

- A fingerprint is the entry's identity and can't be edited after saving — delete
  and re-add to block a different certificate.
- Blocking takes effect on **Apply** (in place).
- Revocation is fingerprint-based, so it targets one specific certificate; if you
  re-sign a node with a new certificate, that new one is not blocked.
