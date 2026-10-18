# Pangolin Newt integration (draft)

Provides a single-site connector under VPN → Pangolin Newt, with MVC/API
configuration, service controls, log access and standalone configuration import.
Credentials are included in OPNsense config.xml backups. The generated runtime
JSON is root-only; credentials are not passed as command-line arguments.

## Integration prerequisite

This draft requires a FreeBSD port for fosrl/newt. `PLUGIN_DEPENDS=newt` is a
provisional package name, not a reference to the unrelated devel/newt library.
Before integration, confirm the dependency name, /usr/local/sbin/newt executable
path, --config-file support and rc.d ownership with the port maintainer. This
plugin currently supplies /usr/local/etc/rc.d/newt; the dependency must not
install a conflicting file. No binary or binary download script is included.

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
