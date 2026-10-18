# Pangolin Newt integration (draft)

Provides a single-site connector under VPN → Pangolin Newt, with MVC/API
configuration, service controls, log access and standalone configuration import.
Credentials are included in OPNsense config.xml backups. The generated runtime
JSON is root-only; credentials are not passed as command-line arguments.

## Integration prerequisite

The plugin depends on the `pangolin-newt` package from the proposed
`net/pangolin-newt` port: https://github.com/opnsense/ports/pull/301.
The port builds Newt 1.18.1 from source with Go 1.26+ and installs
`/usr/local/sbin/newt`; upstream supports the `--config-file` interface.
The package name avoids the unrelated `devel/newt` library.

The plugin owns `/usr/local/etc/rc.d/newt` and generated configuration;
the dependency supplies only the executable (plus framework license metadata).
No precompiled binary or binary download script is included.
The port must be merged and the package published before this plugin can be
installed from the official repository.

Plugin code is BSD-2-Clause. The separately packaged Newt executable is AGPLv3.

## Validation

Run `python3 tests/templates.py` with Jinja2 installed. Run `php tests/model.php`
on OPNsense with its MVC framework available. The original local package's
validation notes report API/service/model tests on OPNsense 26.7.6_3; those
results do not validate this upstream packaging adaptation.

Before marking this draft ready, build the plugin with the actual port and test
clean install, upgrade, config import, apply, start/stop, uninstall and reboot
on OPNsense. On 2026-10-18, the user confirmed a successful reboot test of
the existing installation. This does not validate the upstream packaging
adaptation with the future FreeBSD port dependency.

Dependency validation: source cross-builds of Newt 1.18.1 for FreeBSD/amd64
and FreeBSD/arm64 succeeded with vendored modules and GOPROXY=off. Upstream
newtconfig tests passed on macOS. Source distfile checksums and sizes were
verified. Native FreeBSD ports staging/package tests remain outstanding.
