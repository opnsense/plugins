# Local apcupsd startup: light variant

This candidate addresses only NUT startup when an enabled os-apcupsd service on
this firewall provides the selected standalone apcupsd-ups endpoint. The explicit
local names recognised are localhost, 127.0.0.1, ::1 and [::1]. Other local aliases
or interface addresses, remote/unmanaged endpoints, netclient and other drivers
retain their previous startup behavior. Package presence alone is not sufficient.

For that target, generated nut_defer and nut_upsmon_defer use the existing OPNsense
boot mechanism. A sourced NUT setup hook runs the original setup script, then
checks framed NIS status before the native driver precmd. One 30-second readiness
deadline covers localhost resolution, normal rc status/start and protocol polling.
The first resolved address matches the native driver's selection; mixed/non-loopback
localhost results and mismatched enabled NIS bind/port settings are refused.
Custom ports and IPv6 endpoints are preserved.

An enabled absent apcupsd may receive its ordinary rc start command once. An
already running service is not restarted. No configuration or enablement is
changed. Valid on-battery status is left to the existing NUT monitor policy.
COMMLOST, an open port alone and incomplete/invalid NIS responses are not readiness.
The configd start path preserves an initial target-server failure instead of
masking it with a later monitor start. Non-target command order/return semantics
remain the same as the existing plugin.

When readiness times out, startup reports failure and exits. There is no worker
or automatic later retry: an administrator must start again after fixing the
dependency. Native upsdrvctl retry/initialisation settings remain unchanged; its
own startup time is additional to the bounded dependency check. Concurrent manual
starts retain native rc behavior; this is not a global lifecycle lock or supervisor.

No model/form/API change, runtime health service, periodic supervisor, Monit
integration, shutdown wrapper, POWERDOWNFLAG barrier or new volatile state is
included. Native shutdown and flag cleanup remain untouched. In particular,
this does not fix apcupsd-ups runtime COMMLOST/retained-variable representation.
That independent investigation is preserved in the archive branch.

Run `python3 -m unittest discover -s sysutils/nut/tests -v`. Jinja2 is required
for template tests and is already part of OPNsense; it is test-only on macOS.

Validation on 2026-10-16: 31 isolated tests passed on macOS and the same 31 passed
on OPNsense 26.7 / FreeBSD 15.1, without skips. A native package build and real
boot passed using an APC Back-UPS XS 950U connected by USB to apcupsd and NUT
using apcupsd-ups, including a custom NIS port. Repeated start preserved process
IDs and ordinary upsmon startup cleared a valid old laboratory POWERDOWNFLAG.
Simulated dependency tests used real apcupsd with a status-only loopback upstream:
8-second delayed availability succeeded, persistent unavailability exhausted the
30-second readiness budget, and a subsequent manual start succeeded.
Laboratory host shutdown commands were replaced by recording scripts for these
checks. Full shutdown/cold power restoration and physical IPv6 were not tested.

The guard applies to normal rc start. Existing restart and stop actions retain
their native behavior. localhost uses the first resolved address; an IPv4-only
apcupsd listener may require explicitly using 127.0.0.1 if IPv6 is returned first.
This patch does not rewrite the endpoint or change native driver retry settings.

OpenAI Codex (GPT-6) assisted with implementation, tests and documentation.
