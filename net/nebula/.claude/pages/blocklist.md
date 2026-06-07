# Page: Blocklist (`/ui/nebula/blocklist`)

Certificate fingerprints this node refuses to talk to. Each entry blocks one cert
by its sha256 fingerprint, scoped globally or to one instance.

## Files

- Model: `Nebula.xml` → `pki.blocklist.entry` array. Rendered into each instance's
  `pki.blocklist` by `Nebula.php::generateConfig`.
- Page controller: `controllers/OPNsense/Nebula/BlocklistController.php`.
- API controller: `controllers/OPNsense/Nebula/Api/BlocklistController.php`
  (CRUD + `toggle_item`, `block_cert`, `import`, `purge_expired`).
- Forms: `forms/dialogBlocklistEntry.xml`, `dialogBlocklistImport.xml`.
- View: `views/OPNsense/Nebula/blocklist.volt`.

## Maintenance notes

- **Fingerprint** must be 64 lowercase hex (sha256) and is **immutable once
  saved** (changing it would silently re-target the block — add a new entry
  instead). Validated in `addItem`/`setItem`.
- **Scope** is explicit: `global` (every instance) or `instance` (requires an
  instance). Search supports a `__global__` sentinel for the global-only view.
- **Certificate column** resolves the fingerprint to a held cert's description
  (`name: XXXXXXXX`, else `unknown: XXXXXXXX`). Picking a known cert in the dialog
  fills + locks the fingerprint.
- **Block until purged:** the renderer **ignores `expiry`** — an enabled, in-scope
  entry always blocks. `expiry` is only a *purge-eligibility* date. The
  **Purge expired** button is the only thing that removes a past-expiry entry
  (no reference guard — nothing references a blocklist entry). Don't reintroduce a
  render-time expiry skip.
- **Bulk import**: one fingerprint per line (optional `, description`), normalised
  to bare lowercase hex; idempotent at the chosen scope.
- `block_cert` (called from the Certificates page) creates an idempotent global
  block prefilled from a cert's fingerprint + expiry.

## Live test strategy

- Model: `phpunit --filter BlocklistCRUDTest` — covers fingerprint
  validation/immutability, scope filtering, block-until-purged render, and
  `purgeExpiredBlocklist` (incl. non-ISO date parsing).
- Browser: add an entry, bulk import, block-from-cert, set a past expiry and Purge
  expired; confirm an expired-but-unpurged entry still blocks (rendered config /
  `nebula -test`).
