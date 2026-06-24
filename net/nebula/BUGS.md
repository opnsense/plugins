# Known issues & limitations

Known bugs, limitations, and upstream-blocked items in os-nebula. Most are
limitations of the underlying `nebula` daemon rather than of the plugin.

## create-tunnel: no explicit remote address (upstream, PR #1749)

On the **Tunnels** page, creating a tunnel to a peer resolves the peer's address
through the lighthouse; you cannot create a tunnel to an **explicit remote
address** in one step.

- **Why:** `nebula`'s debug command `create-tunnel -address <ip:port> <vpn>`
  SEGV-panics the daemon on affected releases. To avoid crashing a running
  instance, the plugin issues `create-tunnel <vpn>` only (lighthouse-resolved).
- **Workaround:** let the lighthouse resolve the peer (the normal case), then use
  **Change remote** (`change-remote`, which does *not* crash) to point the tunnel
  at a specific underlay address.
- **Status:** fixed upstream in [slackhq/nebula PR #1749](https://github.com/slackhq/nebula/pull/1749).
  Once that fix is in a released `nebula`, the plugin will restore the
  `-address` form. The exact restore steps live in the `PENDING` comment on the
  `create-tunnel` branch of `nebula_instance_debug()` in
  `src/opnsense/scripts/OPNsense/Nebula/setup.php`.

## No per-peer byte counters (upstream limitation)

The Tunnels page cannot show per-peer throughput (rx/tx bytes). `nebula` exposes
only a per-peer message counter, not byte counts, so there is nothing to display.

---

Found something not listed here? Please open an issue (and, for daemon-level
behaviour, check whether it reproduces with `nebula` directly — it may belong
upstream).
