# Page: Status (`/ui/nebula/status`)

Per-instance health summary + service controls. Links to Tunnels for live peers.

## Files

- Page controller: `controllers/OPNsense/Nebula/StatusController.php`.
- API: `Api/InstanceController.php` (`snapshot`, `debug`), `Api/ServiceController.php`
  (start/stop/restart + `dirty`/`reconfigure`).
- View: `views/OPNsense/Nebula/status.volt`.
- Backend: `scripts/OPNsense/Nebula/setup.php` — `snapshot` builds the per-instance
  status (running/pid, config-valid, tun device + addresses via `device-info`,
  resolved certificate summary, listen host:port).

## Maintenance notes

- The snapshot is a one-shot fan-out over all instances; it does **not** query the
  hostmaps (live peers moved to Tunnels — that was the real scaling fix). Keep
  heavy/per-peer work off this page.
- **Listen** is rendered host:port with IPv6 literals bracketed
  (`nebula_format_listen` → `[::]:4242`) — `::` and `:::4242` are different
  strings; keep the bracketing.
- Body shows running/pid, config-valid (plain text, no green), Interface, cert
  expiry. Watch text contrast (avoid hard-to-read grey on grey).
- Service start/restart/stop use the page-header service controls
  (`updateServiceControlUI('nebula')`), rendered on load.
- An Apply on any page goes through `service/reconfigure` → `setup.php apply`
  (HUP reload, see `pages/instances.md`); the dirty marker raises the apply notice
  until reconfigured.

## Live test strategy

- No model tests. Browser: confirm the snapshot reflects running vs stopped,
  config-valid, the bracketed Listen, and cert expiry; exercise the
  start/restart/stop controls and confirm the apply-needed notice clears after
  Apply. A quick backend check: `configctl nebula snapshot` returns the per-instance
  JSON.
