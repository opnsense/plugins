# Page: Tunnels (`/ui/nebula/tunnels`)

Live peer/tunnel view across all instances. No model — data comes from the
always-on nebula sshd debug server.

## Files

- Page controller: `controllers/OPNsense/Nebula/TunnelsController.php`.
- API controller: `controllers/OPNsense/Nebula/Api/PeerController.php`
  (`peer/search`).
- View: `views/OPNsense/Nebula/tunnels.volt`.
- Backend: `scripts/OPNsense/Nebula/setup.php` — `peers_all` (configd
  `peers_all`) fans out `list-hostmap -json` + `list-pending-hostmap -json` across
  running instances **in parallel** (proc_open + stream_select, per-process
  timeout) and returns instance-tagged rows; `debug` for per-peer actions.

## Maintenance notes

- **Static (client-side) UIBootgrid:** built once with
  `UIBootgrid({datakey, options:{ajax:false, …, formatters:{…}}})`, then data is
  pushed with `bootgrid('replace', rows)` (it **throws on an empty array** — use
  `'clear'`). Direct `$.fn.bootgrid({…})` is unsupported.
- One grid for all instances + an instance filter + search + a manual Refresh
  (no auto-poll — a lighthouse can have thousands of peers). Static-mode
  search/sort act on row DATA, so ship a searchable string field for any
  compact-rendered column.
- Per-peer buttons via `$(document)` delegation (UIBootgrid replaces the `<table>`
  with a `<div>`): Close / Query-LH / Change-remote / Connect. **Handshaking**
  (pending) rows are folded into the same grid and show only Query-LH.
- `list-hostmap -json` already carries **groups** (`cert.details.groups`), **all
  known remotes** (`remoteAddrs`), and `messageCounter` for every peer in ONE
  call — do NOT add per-peer `print-cert`/`print-tunnel` enrichment. Nebula
  exposes **no rx/tx bytes** (only `messageCounter`).
- `change-remote` uses `-address <ip:port> <vpn>`; `create-tunnel` is **vpn-only**
  (its `-address` form SEGV-panics older nebula). The full note — why, and how to
  restore the `-address` form once the upstream fix ships — is the `PENDING`
  comment on the `create-tunnel` branch of `nebula_instance_debug` in `setup.php`
  (slackhq/nebula PR #1749).
- `stream_select` needs a non-negative timeout: clamp with `max(0.0, …)`.

## Live test strategy

- No model tests. Needs a real second peer: bring up a peer, let it handshake,
  then exercise every action button (Close / Query-LH / Change-remote / Connect)
  and confirm the handshaking-row path. Re-validate the debug command surface
  after a nebula upgrade (`.claude/nebula-upgrade.md`). See the debug-server
  section of `.claude/manual-test-procedures.md`.
