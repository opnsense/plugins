# os-nebula — agent guide

OPNsense GUI plugin for the [Nebula](https://github.com/slackhq/nebula) mesh
overlay VPN. Multi-instance: each instance is one `nebula` daemon with its own
config, certificate, and tun device. This file is the entry point for working in
this plugin; deeper runbooks live in `.claude/`.

> Keep everything in this repo generic and public-safe. No site-specific IPs,
> hostnames, or infrastructure details — those belong in local notes, never in
> committed files.

## Layout

Files under `src/` install relative to `/usr/local/` on the appliance:

| Repo path | Installs to | What |
|---|---|---|
| `src/opnsense/mvc/app/models/OPNsense/Nebula/` | `/usr/local/opnsense/mvc/app/models/…` | model (`Nebula.xml`, `Nebula.php`, `ConfigMap.php`), ACL, Menu |
| `src/opnsense/mvc/app/controllers/OPNsense/Nebula/` | `…/controllers/…` | page controllers + `Api/` controllers + `forms/` dialog XML |
| `src/opnsense/mvc/app/views/OPNsense/Nebula/` | `…/views/…` | Volt page templates |
| `src/opnsense/scripts/OPNsense/Nebula/` | `…/scripts/…` | `setup.php` (daemon lifecycle), `pki.php` (nebula-cert) — run by configd |
| `src/opnsense/service/conf/actions.d/actions_nebula.conf` | `…/service/conf/actions.d/` | configd actions |
| `src/opnsense/mvc/tests/` | `…/mvc/tests/` | phpunit model tests |
| `src/etc/inc/plugins.inc.d/nebula.inc` | **`/usr/local/etc/inc/plugins.inc.d/`** | plugin hooks (services, syslog, firewall, devices, interfaces, configure) |
| `src/etc/rc.syshook.d/start/20-nebula` | `/usr/local/etc/rc.syshook.d/start/` | boot start hook |

**Deploy-path gotcha:** `src/opnsense/…` → `/usr/local/opnsense/…`, but
`src/etc/…` → **`/usr/local/etc/…`** (NOT `/usr/local/opnsense/etc/…`). Putting
`nebula.inc` under the wrong path makes the hooks silently never fire, and
`php -l` still passes on the misplaced copy. `make install` handles this; only
hand-deploys get it wrong.

## Build / deploy to a test box

Develop against a disposable OPNsense VM. Two ways to get code onto it:

- **Full install** (mirrors `make install`): from `src/`, stream the tree to
  `/usr/local/`:
  ```sh
  tar -cf - -C src . | ssh root@TESTBOX 'tar xf - -C /usr/local'
  ```
- **Single files**: `scp` to the mapped path (mind the `src/etc` → `/usr/local/etc`
  rule above).

After deploying **PHP that the GUI runs** (controllers, models, `nebula.inc`),
clear opcache or the change won't take effect — the GUI runs php-cgi with
`opcache.validate_timestamps=0`:
```sh
ssh root@TESTBOX 'configctl webgui restart'   # cycles php-cgi workers, clears opcache
```
`.volt` templates and form/model XML are read fresh per request (no restart
needed). After deploying `nebula.inc`, the webgui restart also reloads
`plugins_devices()`/`plugins_interfaces()`.

macOS `tar` prints harmless `com.apple.provenance` xattr warnings (exit 1) but
extracts fine; pass `--no-mac-metadata` and delete stray `._*` files on the box.

The appliance root shell is **csh** — wrap remote shell logic as
`ssh root@TESTBOX 'sh -s' <<'EOF' … EOF` (redirects/`for`/`&&` misbehave under
csh).

## Test

- **Model unit tests (phpunit):** `cd /usr/local/opnsense/mvc/tests && phpunit
  app/models/OPNsense/Nebula` (or `--filter <Class>`). Tests instantiate
  `new Nebula()` against a fixture config; they cover the data paths the API
  controllers use. Controller HTTP paths and configd scripts are NOT unit-tested
  — verify those live (see `.claude/manual-test-procedures.md`).
- **Live model checks:** bootstrap the MVC env in a CLI script and exercise the
  model directly — see the pattern in `.claude/manual-test-procedures.md`
  ("live bootstrap recipe").
- **Manual / browser acceptance:** `.claude/manual-test-procedures.md`.

## Idioms that bite (full detail in `.claude/`)

- **Grid columns:** `getFormGrid()` makes a column for EVERY form field with an
  `<id>` unless it has `<grid_view><ignore>true</ignore></grid_view>`. You cannot
  prune columns by removing `grid_view` (that ADDS them). Curate grids in the page
  controller (whitelist), as `InstancesController` does.
- **Live/non-model grids:** use `UIBootgrid({datakey, options:{ajax:false,…}})`
  + `bootgrid('replace', rows)` (throws on empty → use `'clear'`). Direct
  `$.fn.bootgrid({…})` is unsupported on OPNsense.
- **`InterfaceField` defaults:** a hard `<Default>wan</Default>` throws on boxes
  with no `wan`; default empty and pre-select in JS.
- **Idempotent daemon start:** `setup.php start [<uuid>]` guards on a live pid;
  don't launch a second daemon for a running instance.
- **Apply = reload, not restart:** Apply HUP-reloads each instance in place; only
  a structural change (listen host/port, cipher, tun dev) restarts. See
  `.claude/pages/instances.md` and the reload design doc.

## Per-page guides

Each UI page has a maintenance + live-test guide under `.claude/pages/`:
`instances`, `authorities`, `certificates`, `blocklist`, `routes`, `firewall`,
`tunnels`, `status`. Read the relevant one before changing a page.

## Upgrading the bundled nebula

See `.claude/nebula-upgrade.md` — covers the FreeBSD `security/nebula` port, the
`nebula` / `nebula-cert` binaries, config-schema (knob) drift, and the debug
server command surface.
