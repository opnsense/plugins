# Manual / live test procedures

Model data paths are covered by phpunit. Everything else — configd scripts, the
daemon lifecycle, the debug server, the Volt UI, and OPNsense integration
(interfaces, firewall) — is verified live on a disposable OPNsense test box. This
file holds the cross-cutting procedures; per-page checks live in
`.claude/pages/<page>.md`.

## Standard loop

1. Deploy changed files (see `AGENTS.md` → Build / deploy).
2. If you touched GUI PHP (controllers, models, `nebula.inc`):
   `configctl webgui restart` (clears opcache). `.volt` and form/model XML are
   read fresh — no restart needed for those.
3. Hard-refresh the browser (Cmd/Ctrl-Shift-R) to drop cached page JS.
4. Exercise the change; check `nebula` syslog and the daemon state.

## Live model-bootstrap recipe (CLI, no browser)

To exercise model logic against a scratch config without the HTTP layer, bootstrap
the MVC env in a CLI PHP script (standard appliance paths only):

```php
require_once '/usr/local/opnsense/mvc/app/config/AppConfig.php';
$scratch = '/tmp/scratch-conf'; @mkdir($scratch, 0700, true);
copy('/usr/local/opnsense/mvc/tests/app/models/OPNsense/Nebula/NebulaConfig/config.xml',
     $scratch . '/config.xml');
$config = new OPNsense\Core\AppConfig([
  'application' => [
    'baseUri' => '/', 'controllersDir' => '/usr/local/opnsense/mvc/app/controllers/',
    'modelsDir' => '/usr/local/opnsense/mvc/app/models/', 'viewsDir' => '/usr/local/opnsense/mvc/app/views/',
    'pluginsDir' => '/usr/local/opnsense/mvc/app/plugins/', 'libraryDir' => '/usr/local/opnsense/mvc/app/library/',
    'contribDir' => '/usr/local/opnsense/contrib', 'configDefault' => $scratch . '/config.xml',
    'configDir' => $scratch, 'cacheDir' => '/tmp/sc', 'tempDir' => '/tmp/st',
  ],
  'globals' => ['debug' => false, 'owner' => 'root:wheel', 'simulate_mode' => false],
]);
require_once '/usr/local/opnsense/mvc/app/config/loader.php';
@mkdir('/tmp/sc', 0700, true); @mkdir('/tmp/st', 0700, true);
use OPNsense\Nebula\Nebula;
$m = new Nebula();
// … add nodes, call model methods, assert …
```

Notes:
- Assign the `AppConfig` to a variable and keep it; the autoloader relies on it.
  Without it `new Nebula()` throws "class not found".
- `scp` the script to the box and run it; do **not** build it via a nested
  here-doc (the inner here-doc mangles the bootstrap).
- Use a scratch `configDir` (never `/conf`) so a test never touches the appliance
  config.
- `serializeToConfig()` validates relation fields; an in-memory `trusted_cas` /
  `certref` to a freshly-added node can raise a validation error in this harness.
  Assert on the model in memory, or save+reload, rather than round-tripping
  relation fields through serialize.

## Daemon lifecycle (any change to `setup.php` / `nebula.inc`)

Per instance, confirm:
- **start** creates the tun and a live pid; `nebula -test -config <file>` passes.
- **Apply** (`configctl nebula reconfigure`) HUP-reloads in place — pid unchanged,
  tunnels stay up — unless a structural key (listen host/port, cipher, tun dev)
  changed, which restarts that instance (new pid).
- **restart** (`configctl nebula restart [<uuid>]`) recreates the tun.
- **stop** kills the daemon and destroys the tun (no orphan `nebulaX` lingers).
- No duplicate daemons: exactly one `nebula` process per enabled instance.

## OPNsense interface integration (the `_devices` / `_interfaces` hooks)

After assigning an instance's tun in Interfaces → Assignments:
- It lists as `<dev> (Nebula - <descr>)`; **no IP config** is offered
  (`configurable=false`); the cert-derived address shows on the interface.
- Enabling the assigned interface yields a firewall tab for it.
- "Nebula (Group)" is selectable as a rule Interface (Firewall → Rules), matching
  every nebula tun via the kernel `nebula` group.
- **Reboot test (boot ordering):** with an instance assigned + the interface
  enabled, reboot. It must come up clean — assigned interface UP, tun recreated,
  no "interface mismatch" warning. This proves `nebula_prepare()` creates the
  device before interface bring-up.
- Disabling the instance removes its device from Assignments (the assignment
  orphans gracefully); re-enabling restores it. The device name is system-managed
  and not user-editable.

## Debug-server / live-peer pages

Status and Tunnels read the always-on nebula sshd debug server. Validate every
per-peer action button against a real second peer (handshake, then
close/query-lighthouse/change-remote/connect). Some commands can crash older
daemon versions — re-validate after a nebula upgrade (`.claude/nebula-upgrade.md`).
