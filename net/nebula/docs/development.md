# Developing the plugin

A guide for people hacking on os-nebula. (If you use an AI coding assistant, it
will also pick up `AGENTS.md` and `.claude/` at the plugin root — those are the
same knowledge written for tools. This document is the human-readable version and
stands on its own.)

## Architecture

It's a standard OPNsense MVC plugin plus a couple of system pieces:

- **Model** (`src/opnsense/mvc/app/models/OPNsense/Nebula/`) — `Nebula.xml`
  defines the config schema (a `general` block, an `instances` array, and a `pki`
  subtree for CAs/certs/blocklist, plus routes and firewall rules). `Nebula.php`
  holds logic, most importantly `generateConfig()` which renders an instance's
  Nebula YAML, and `ConfigMap.php` which maps model fields to YAML paths.
- **Controllers** (`…/controllers/OPNsense/Nebula/`) — one page controller per UI
  page, and an `Api/` controller per resource (the AJAX/CRUD endpoints). Dialog
  forms are XML under `forms/`.
- **Views** (`…/views/OPNsense/Nebula/`) — Volt templates, one per page.
- **Scripts** (`src/opnsense/scripts/OPNsense/Nebula/`) — `setup.php` manages the
  daemon lifecycle (start/stop/restart/apply, render, validate) and `pki.php`
  wraps `nebula-cert`. These are invoked by **configd** (see
  `service/conf/actions.d/actions_nebula.conf`).
- **Hooks** (`src/etc/inc/plugins.inc.d/nebula.inc`) — integrate with OPNsense:
  services, syslog, the underlay firewall rule, and the interface registration
  (`_devices` / `_interfaces` / `prepare`) that makes a tunnel an assignable
  interface.

Each running instance is one `nebula` daemon (under `/usr/sbin/daemon`) with its
own rendered config and tunnel device.

## Repo layout → install paths

Files under `src/` install relative to `/usr/local/`:
`src/opnsense/…` → `/usr/local/opnsense/…`, and `src/etc/…` →
**`/usr/local/etc/…`** (not under `/usr/local/opnsense/`). `make install` handles
this.

## Build, install, iterate

- Develop against a disposable OPNsense VM.
- Install via the standard plugin build (`make install` from the plugin
  directory), or copy `src/` onto the box under `/usr/local/` for quick
  iteration.
- After changing PHP that the GUI runs (controllers, models, `nebula.inc`),
  restart the web GUI so the new code loads (`configctl webgui restart`); Volt
  templates and form/model XML are picked up without a restart.

## Testing

- **Unit tests** (PHPUnit) live in `src/opnsense/mvc/tests/`. On the box:
  `cd /usr/local/opnsense/mvc/tests && phpunit app/models/OPNsense/Nebula`. They
  exercise the model/render logic against a fixture config.
- **The config renderer is the most test-worthy part** — `GenerateConfigTest`
  pins the emitted Nebula YAML. Add cases there when you change rendering.
- **Live/manual testing** matters because the configd scripts, the daemon
  lifecycle, the GUI, and OPNsense integration aren't unit-tested. Validate a
  rendered config with `nebula -test -config <file>` and exercise the daemon
  (start → apply → restart → stop). For UI/integration changes, test in a browser
  on the VM.

## Conventions

- Match the surrounding code style; keep each file focused.
- New source files carry the BSD 2-Clause header used throughout.
- Keep site-specific details (your test box, your network) out of committed files.
- Prefer small, reviewable changes; the renderer and the daemon lifecycle are the
  load-bearing parts — test them.

## Where to look first

- Changing a page's behaviour → that page's controller + view + form, and (for
  rendered config) `generateConfig()`/`ConfigMap.php`.
- Adding a config knob → a model field + a `ConfigMap` entry (or a hand-rendered
  block in `generateConfig`) + a `GenerateConfigTest` case.
- Daemon/interface behaviour → `setup.php` and `nebula.inc`.
- Upgrading the bundled Nebula → check config-schema drift against the new
  release and re-run the renderer tests.
