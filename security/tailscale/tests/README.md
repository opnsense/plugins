# Tailscale startup regression tests

From the root of this checkout, run:

```sh
sh security/tailscale/tests/run.sh
```

The runner also accepts an absolute path from another directory. It needs a
POSIX shell, Docker with Linux containers, curl and sha256sum, shasum or FreeBSD
sha256. No host PHP, Composer or firewall credentials are needed. Run it on a
development machine, not a firewall.

## References and isolation

Preparation downloads seven public files from opnsense/core at commit
`228ab68d54513046f79bc36d7f583936922d1951` (26.7.9), verifying every file against
`core.sha256`. The PHP 8.5 image is also pinned by digest. Downloaded references
and generated include stubs live in a temporary directory removed on exit;
the checkout is not modified. No empty include fixtures are committed.

An existing core checkout can supply the references instead. The same hashes
are required. With that checkout and the PHP image cached, run offline:

```sh
sh security/tailscale/tests/run.sh --core /path/to/opnsense-core --offline
```

`--offline` refuses to download either references or an image. Without
`--offline`, `--core` still uses the supplied files without changing them.

The test container always uses `--network none`, a read-only root and read-only
plugin/core/include mounts, no capabilities, the calling user's numeric UID/GID,
and a disposable /tmp tmpfs. No Docker socket, host service paths, configuration
or credentials are mounted. No daemon or monitor is actually started.

## What runs

The current production shell hook runs with only its pluginctl executable path
redirected to `scenario.php`. That fixture executes the full pinned pluginctl,
the current plugin include, and unchanged function bodies extracted with PHP's
tokenizer from the core dispatcher, interface helpers and dpinger plugin.
Models and interface/process state are synthetic; host-route updates, process
signals and commands are intercepted. A full interface-renewal request fails
the test. No configd socket simulator or renewal-script fixtures are needed.

Start/Restart tests read the actual configd action command, substitute a native
service boundary with a chosen exit code, and execute the same shell hook.
The core gateway model and FreeBSD runtime are not emulated in full.

## Coverage

- Scoped monitor startup, IPv4/IPv6 and dual-stack sources, one watcher HUP.
- Disabled plugin/interface/gateways, monitor_disable, missing assignment and
  empty selection without touching WAN/WireGuard monitors.
- Different logical assignments, multiple gateways, inactive gateways, global
  IPv6 disable, ULA/GUA and unavailable or unusable IPv6 sources.
- Native Start/Restart failures preserving distinct exit codes and bypassing
  the callback; successful native calls executing the actual hook.
- Repeated calls, original boot ordering, production syntax and hook permissions.

Unavailable addresses follow the native dpinger contract: monitors are skipped
and logged, while pluginctl can return success. These tests deliberately assert
that behavior rather than the previous helper's explicit readiness failure.

Expected result: **30 passed, 0 failed, 30 total**, exit status zero. A failed
assertion prints its name and exits nonzero. No checks are skipped. This standalone
runner is separate from the repository's PHPUnit-oriented `make test` target.

The suite does not establish real boot readiness, connectivity, IPv6 runtime
behavior, LAN forwarding, failover or uninterrupted traffic. It does not model
later gateway-watcher alarms. The separate real IPv4 Restart/boot evidence in
the PR applies only to the stated environment. Deployment and cleanup tools
are outside this PR. New harness code is BSD-2-Clause licensed (see LICENSE);
downloaded core code retains its upstream licensing.
