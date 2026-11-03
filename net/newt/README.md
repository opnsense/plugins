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

On 2026-10-18, the PR sources were tested on OPNsense 26.7.7,
FreeBSD 15.1-RELEASE-p4, amd64:

- Native source build of pangolin-newt 1.18.1 using Go 1.26.9; source
  checksums, check-plist, stage-qa and package creation passed.
- Plugin package creation, PHP syntax, model validation and configuration
  template tests passed.
- Both packages installed and uninstalled in an isolated root with scripts
  disabled; dependency, checksum and file ownership checks passed.
- Live installation of os-newt-devel 1.0 plus the proposed dependency passed,
  including template reload and configctl restart/stop/start. The child
  process stayed alive and logs reported successful tunnel establishment.
- The previously installed os-newt-devel 1.1 and its dependency were restored.
  Package dependency/checksum checks passed; config.xml and runtime JSON were
  byte-identical to their backups. Runtime JSON remained root-owned, mode 0600.

The build reused the target's existing ports framework and standalone Go
compiler with explicit GO_CMD/BINARY_ALIAS and NO_DEPENDS=yes. It was not a
clean poudriere build or an automated compiler dependency installation test.
The live test preserved an existing configured site; first-install credential
migration and an authenticated WebUI/API test were not performed in this run.
No reboot was performed during this run. The user's earlier successful reboot
report applies to the existing installation, not this PR package pair.

Tested plugin commit: a954eaa0d453e9335ae66397ba7a718eabf7f118.
Tested port commit: 93a8b8523606822e1cc90b2f0cc0ae2ac91fc599.
The identical port source is proposed to FreeBSD in PR #643.

To rerun the focused tests, use `python3 tests/templates.py` with Jinja2 and
`php tests/model.php` on OPNsense with its MVC framework available.
