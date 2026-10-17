# Tailscale startup regression tests

From the root of a checkout of this branch, run:

```sh
sh security/tailscale/tests/run.sh
```

From another directory, pass the absolute path to `tests/run.sh` instead.
The harness resolves the plugin under test relative to the script; it needs no
developer workspace layout, installed OPNsense system or firewall credentials.

Requirements: a POSIX shell, Docker with Linux containers, `curl`, and one of
`sha256sum`, `shasum` or FreeBSD `sha256`. No host PHP or Composer installation is
needed. Run this on a development machine, not on a firewall.

## Preparation and offline execution

The preparation step downloads 12 public files from `opnsense/core` at commit
`cdd7a38717b67ce90b673bd528ddaf2b2b42d8d7`. Every file is checked against
`core.sha256` before its code is loaded. A missing or mismatched reference fails
the run. The PHP 8.5 CLI image is also pinned by digest in `run.sh` and downloaded
only if it is not cached. Network access is needed for this preparation only.
Downloaded references go into a temporary directory removed when the script exits.
The checkout is not modified.

An existing core checkout can supply the references instead. Its files must match
the pinned hashes; the script never checks out or edits that directory:

```sh
sh security/tailscale/tests/run.sh --core /path/to/opnsense-core
```

For a completely offline run, first obtain that core revision and cache the PHP
image specified in `run.sh`, then run:

```sh
sh security/tailscale/tests/run.sh --core /path/to/opnsense-core --offline
```

`--offline` refuses to download either references or an image. This requires the
same 12 files and cached image as an online preparation.

## Isolation and implementation

The test container always runs with `--network none`, a read-only root filesystem,
read-only plugin/core mounts, no capabilities, and the calling user's numeric
UID/GID. Its only writable filesystem is a disposable `/tmp` tmpfs; it has no
Docker socket, host `/usr/local`, configuration, service socket or credentials
mounted. Test subprocesses and the local Unix socket used by the Backend tests
remain inside that container. No daemon or gateway monitor is started.

`run.php` executes the current plugin include, actual PHP boot hook and commands
read from `actions_tailscale.conf`. `scenario.php` provides synthetic configuration,
gateway-model and runtime-interface boundaries. It extracts named functions from
the hash-verified core sources with PHP's tokenizer and executes them unchanged;
the real `rc.newwanip`/`rc.newwanipv6` scripts run with their includes and system
effects intercepted. Command execution, process signalling, routing, resolver,
firewall reload and VPN notifications become trace events asserted by the tests.

The real core `Backend` class is tested against a local Unix socket server which
acknowledges submission while deliberately holding the simulated action open.
This checks parameter escaping and the detach flag; no real configd is involved.

The `includes/` files are empty include boundaries, not copies of production
implementations. Copied/extracted core code retains its upstream licensing in the
downloaded files. New harness code is BSD-2-Clause licensed (see `LICENSE`).

## Coverage and expected result

Expected result with this revision: **57 passed, 0 failed, 57 total**, exit status
zero. A failed assertion prints its test name and exits nonzero. No test is skipped.

- Disabled/unassigned interfaces; disabled or unmonitored gateways; empty selection.
- IPv4, IPv6 and dual-stack; partial readiness; ULA and documentation GUA;
  tentative, detached, deprecated and link-local IPv6; global IPv6 disable.
- One request per selected family, forced renewal with an unchanged address,
  invalid/empty/error acknowledgements and exceptions, independent family failures,
  bounded repeated calls and no recursive callback.
- Native Start/Restart failures preserving distinct error codes, successful native
  calls invoking the actual hook, callback errors and no-op exit status.
- Core monitor selection by interface/family, source-address selection,
  `monitor_disable`, empty monitor maps, firewall/resolver/VPN effects, IPv4 cache
  behaviour/state deletion, IPv6 DAD invocation, routing input and boot order.
- Actual Backend request framing, syntax of both production PHP files and the
  boot hook's executable permission.

This suite covers the submitted plugin code. Trial installation, rollback and
evidence collection tools are outside this PR and are not counted here.

These are isolated regressions against one pinned core revision. The gateway model,
configuration and FreeBSD interface data are simulated. They do not establish real
boot readiness, dpinger connectivity, live IPv6 behaviour, uninterrupted traffic,
or the effect of both notifications on a real dual-stack firewall. Separate real
IPv4 tests described in the PR do not extend the scope of these simulations.
