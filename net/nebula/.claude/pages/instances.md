# Page: Instances (`/ui/nebula/instances`)

The core page: one row per nebula daemon instance. Owns the daemon lifecycle, the
rendered config, the tun device, and the OPNsense interface integration.

## Files

- Model: `models/OPNsense/Nebula/Nebula.xml` (`instances.instance` array + the
  `general` block), `Nebula.php` (`generateConfig`, `serializeToConfig`,
  `assignDeviceNames`, `sshdPortFor`), `ConfigMap.php` (field→yaml map).
- Page controller: `controllers/OPNsense/Nebula/InstancesController.php` — builds
  the grid columns explicitly (see Maintenance).
- API controller: `controllers/OPNsense/Nebula/Api/InstanceController.php`
  (search/get/add/set/del/toggle + `checkUniqueListener`, `reload`).
- Dialog form: `controllers/OPNsense/Nebula/forms/dialogInstance.xml`.
- View: `views/OPNsense/Nebula/instances.volt`.
- Daemon/config: `scripts/OPNsense/Nebula/setup.php` (start/stop/restart/apply,
  render, validate, debug).
- Hooks: `etc/inc/plugins.inc.d/nebula.inc` (`nebula_services`, `nebula_firewall`,
  `nebula_devices`, `nebula_interfaces`, `nebula_prepare`, `nebula_configure`).

## Maintenance notes

- **Grid columns are built in the controller, not the form.** `getFormGrid()`
  makes a column for every form field with an `<id>` (unless
  `grid_view/ignore=true`), so the page would otherwise show the whole config
  surface. `InstancesController::indexAction` takes `getFormGrid()`'s output and
  assembles an explicit whitelist (Enabled, Interface, Description, Lighthouse,
  Listen, Certificate, Status). `Listen` and `Status` are computed columns
  (formatters `nebula_listen` / `nebula_status`); `Interface` is injected
  (sourced from `tun_name`, which the search action returns). To change visible
  columns, edit that whitelist — not the form.
- **Device name (`tun_name`) is system-managed.** Auto-assigned at save by
  `Nebula::assignDeviceNames()` as `nebula` + `substr(md5(uuid),0,6)` — derived
  from the immutable instance UUID, so it is stable and never reused. It is NOT in
  the dialog (removed, like WireGuard's `interface` field) and not user-editable;
  it shows only as the Interface grid column. Don't reintroduce it as an editable
  field — renaming a device that's assigned would silently break the assignment.
- **Apply reloads, doesn't restart.** `[reconfigure]` → `setup.php apply` →
  per-instance HUP reload via the debug server; only a structural change (listen
  host/port, cipher, tun dev) restarts. Keep `nebula_needs_restart`'s structural
  key set in sync with what nebula can apply live.
- **OPNsense interface integration:** `nebula_devices` (assignable, labelled,
  `configurable=false`, `volatile=true`), `nebula_prepare` (creates the tun at
  interface bring-up — nebula owns tun creation, so prepare starts the daemon and
  waits, it can't pre-create), `nebula_interfaces` (the "Nebula (Group)" firewall
  group). The tun joins the kernel `nebula` group on start.
- **`firewall_interfaces`** drives `nebula_firewall` (auto inbound rule for the
  UDP listen port). Default empty; the dialog pre-selects WAN on Add via JS (a
  hard `InterfaceField` default of `wan` would fail validation on boxes without a
  `wan` interface).
- Per-instance start is idempotent (guards on a live pid). Don't start a second
  daemon for a running instance.

## Live test strategy

- Model: `phpunit --filter 'InstanceCRUDTest|GenerateConfigTest|InstanceUniqueListenerTest'`.
  Covers naming stability, unique listener/device, and the rendered YAML.
- Daemon lifecycle + interface integration + reboot: see the corresponding
  sections of `.claude/manual-test-procedures.md`.
- Quick render check on the box: render an instance and run
  `nebula -test -config <file>`.
- After editing `InstancesController` or `nebula.inc`: `configctl webgui restart`
  (opcache) then confirm the grid columns and Assignments labels in the browser.
