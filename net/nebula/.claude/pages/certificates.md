# Page: Host Certificates (`/ui/nebula/certificates`)

Nebula host certificates — the identity an instance presents. Signed under a CA
(Authorities page), CSR-signed, or imported.

## Files

- Model: `Nebula.xml` → `pki.certificates.certificate` array.
- Page controller: `controllers/OPNsense/Nebula/CertificatesController.php`.
- API controller: `controllers/OPNsense/Nebula/Api/CertificateController.php`
  (search/get/del + `sign`/`sign_csr`, `import`, `info`, `generate_file`,
  `purge_expired`; `certReferencedBy`).
- Forms: `forms/dialogCertificate.xml`, `dialogCertificateSign.xml`,
  `dialogCertificateImport.xml`.
- View: `views/OPNsense/Nebula/certificates.volt`.
- PKI script: `scripts/OPNsense/Nebula/pki.php` (`sign-cert`), via the
  `pki_sign_cert` configd action.

## Maintenance notes

- **Sign** generates a key + cert under a chosen CA via `nebula-cert sign`
  (configd `pki_sign_cert`). Empty duration = expire just before the CA
  (recommended default).
- **CSR signing** signs from a supplied public key / CSR so the **private key
  never leaves the requesting device**. The result is a **keyless** cert
  (`has_key=0`): it is flagged not-**Assignable** and is **excluded from the
  instance certificate picker** (an instance needs the key to run). The picker
  filter keys off `has_key` — keep that filter when touching the instance form.
- **Import** accepts a pre-signed cert (+ optional key).
- **Fingerprint column** = first 8 hex of sha256; the signing CA shows as
  `name: XXXXXXXX`. `valid_to` is the cert's notAfter.
- **Block this certificate** (per-row) posts to `blocklist/block_cert` — an
  idempotent global blocklist entry prefilled with the fingerprint + expiry.
- **Purge expired** deletes certs past `valid_to`, skipping any still referenced
  by an instance (`certref`) and naming them; single delete uses the same
  `certReferencedBy` guard.

## Live test strategy

- Model: `phpunit --filter CertificateCRUDTest`.
- PKI + CSR semantics: `tools/test_certificate_live.php`, `test_csr_live.php`,
  `test_pki_semantics_live.php` (drive `nebula-cert`).
- Browser: sign a cert; CSR-sign and confirm it is not selectable as an instance
  certificate; import; Block a cert and verify it appears on Blocklist; Purge
  expired (referenced cert skipped).
