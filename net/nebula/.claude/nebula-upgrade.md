# Runbook: upgrading the bundled nebula version

The plugin drives external `nebula` binaries; it does not vendor them. Upgrading
"the nebula version" means: (1) the appliance ships a newer `nebula` /
`nebula-cert`, and (2) the plugin still renders valid configs, drives the daemon,
and speaks the debug protocol for that version.

## Binaries the plugin depends on

- `/usr/local/bin/nebula` — the daemon (`NEBULA_BIN` in `setup.php`).
- `/usr/local/bin/nebula-cert` — PKI (`NEBULA_CERT_BIN` in `pki.php`).
- `/usr/sbin/daemon` — FreeBSD supervisor wrapping the daemon (`DAEMON_BIN`).

These come from the FreeBSD `security/nebula` port (→ the OPNsense pkg set), not
from this repo. So a version bump is really a ports/pkg update; the plugin work
is making sure nothing regressed against the new version.

## Steps

1. **Read the upstream release notes** for every version between the current and
   target nebula release. Note added / changed / removed **config keys**, any
   **`nebula-cert` flag** changes, **debug-server command** changes, and behaviour
   changes to the tun/listen/cipher handling.

2. **Get the new binaries onto a test box** (build the updated port, or install
   the pkg) and confirm `nebula -version` / `nebula-cert -version`.

3. **Audit config-knob coverage.** The plugin renders the nebula YAML from the
   model (`ConfigMap.php` field→yaml map + `Nebula.php::generateConfig`). Drift in
   nebula's config schema is the most common upgrade breakage.
   - `tools/audit_knobs.py` reports handled / deferred / n-a / deprecated knobs
     against a nebula reference config (`tools/nebula_config_reference.yaml`).
   - Update the reference to the new version, re-run, and resolve any newly
     surfaced keys: add a field + ConfigMap entry (or a hand-rendered block) for
     new keys; mark removed keys deprecated.
   - `tools/gen_knobs.py` / `tools/gen_knobs_test.py` back the generated knob set.

4. **Re-run the model tests**, especially the rendered-YAML pins:
   `phpunit app/models/OPNsense/Nebula` (GenerateConfigTest asserts the emitted
   config shape). Update expectations only for intended schema changes.

5. **Validate live config generation:** on the test box, render an instance and
   run `nebula -test -config <file>` (this is exactly what `nebula_validate()`
   does). A new required/renamed key shows up here.

6. **Exercise the daemon lifecycle:** start, Apply (HUP reload), structural-change
   restart, stop. Confirm the tun is created/destroyed and the instance comes back
   (see `.claude/pages/instances.md`).

7. **Re-validate the debug-server surface.** Status/Tunnels drive the always-on
   nebula sshd debug server (`list-hostmap`, `list-pending-hostmap`,
   `reload`, `query-lighthouse`, `change-remote`, `create-tunnel`, `close-tunnel`,
   `device-info`, `print-cert`, …). `ssh … <user>@127.0.0.1 -p <port> help` lists
   the commands the running version supports; diff against what the pages call.
   Watch for renamed/removed commands and for output-format (JSON) changes the
   parsers depend on.

8. **Re-test PKI** (`pki.php` / Authorities / Certificates): generate a CA, sign a
   cert, import, print — `nebula-cert` flags and cert versions can change between
   releases.

## Known version-specific notes

- The `reload` debug command acks `"Reloading config"` then drops the ssh
  connection (exit 255 even on success) — the plugin treats that ack as success.
  Re-check this if the debug protocol changes.
- `create-tunnel -address` crashed the daemon in older releases; the plugin uses
  the address-less form. The full note — and how to restore the `-address` form
  once the fix is in a released nebula — is the `PENDING` comment on the
  `create-tunnel` branch of `nebula_instance_debug` in `setup.php` (slackhq/nebula
  PR #1749). Re-check this on every upgrade.
- Keep any version-pinned assumptions (cipher defaults, initiating cert version,
  blocklist/encrypted-CA features) in step 1's diff.
