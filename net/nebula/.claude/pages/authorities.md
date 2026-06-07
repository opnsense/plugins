# Page: Authorities (`/ui/nebula/authorities`)

Nebula CAs (certificate authorities). Generate or import a CA; sign host certs
against it on the Certificates page.

## Files

- Model: `Nebula.xml` → `pki.authorities.authority` array (`Nebula.php`,
  `ConfigMap.php` not involved — PKI is its own subtree).
- Page controller: `controllers/OPNsense/Nebula/AuthoritiesController.php`.
- API controller: `controllers/OPNsense/Nebula/Api/AuthorityController.php`
  (search/get/del + `generate`, `import`, `info`, `generate_file`,
  `purge_expired`; `caReferencedBy`).
- Forms: `forms/dialogAuthority.xml`, `dialogAuthorityGenerate.xml`,
  `dialogAuthorityImport.xml`.
- View: `views/OPNsense/Nebula/authorities.volt`.
- PKI script: `scripts/OPNsense/Nebula/pki.php` (`generate-ca`, `print-cert`),
  run via the `pki_generate_ca` / `pki_print_cert` configd actions.

## Maintenance notes

- **Generate** calls `nebula-cert ca` through configd (`pki_generate_ca`); the
  resulting crt+key are stored on the model node. Curve (25519/P256), duration,
  groups/networks are passed through.
- **Import** accepts an existing CA cert and (optionally) its key. A **keyless**
  CA (`has_key=0`) can still be trusted but cannot sign — surfaced in the
  "Can sign" grid column.
- **Encrypted CA** (`key_encrypted`): a passphrase is required to sign with it;
  the grid marks it with a lock icon.
- **Fingerprint column** shows the first 8 hex of the sha256 (disambiguates
  like-named CAs). CA references elsewhere render as `name: XXXXXXXX`.
- **Purge expired** (footer button) deletes CAs past `valid_to`, but **skips any
  still referenced** — a cert's `caref` or an instance's `trusted_cas` — and names
  them. Single delete uses the same `caReferencedBy` guard. Don't let purge or
  delete remove a referenced CA.
- Per-row commands: download cert / download key (hidden when `has_key=0`) /
  inspect (parsed `print-cert`).

## Live test strategy

- Model: `phpunit --filter AuthorityCRUDTest`.
- PKI round-trips (generate/import/encrypted) are exercised by the `tools/test_*_live.php`
  guest scripts (they hit `nebula-cert` via configd, which phpunit can't).
- Browser: generate a CA → sign a cert under it (Certificates page) → import a
  keyless CA (confirm "Can sign" = no) → seed an expired CA and Purge expired
  (confirm a referenced one is skipped + reported).
