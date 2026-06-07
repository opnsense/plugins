# Page: Firewall (`/ui/nebula/firewall`)

The **Nebula overlay firewall** — the `firewall:` block inside each instance's
nebula config. This is NOT the OPNsense firewall; it controls traffic *inside* the
mesh, per instance.

## Files

- Model: `Nebula.xml` → `fwrules.rule` array + the per-instance firewall scalar
  knobs (default in/outbound action, conntrack timeouts, default_local_cidr_any).
  Rendered by `Nebula.php::generateConfig` into `firewall.inbound` /
  `firewall.outbound`.
- Page controller: `controllers/OPNsense/Nebula/FirewallController.php`.
- API controller: `controllers/OPNsense/Nebula/Api/FirewallRuleController.php`
  (CRUD + `toggle_item`; `checkCaReferences`).
- Form: `forms/dialogFirewallRule.xml`.
- View: `views/OPNsense/Nebula/firewall.volt`.

## Maintenance notes

- **Deny-by-default:** nebula rejects a config with no `firewall` block, and a
  node with no rules passes nothing. `generateConfig` emits a permissive fallback
  (`port any / proto any / host any`) for a direction that has no enabled rules —
  keep that, or a rule-less instance becomes unreachable.
- Per rule: `direction` (inbound/outbound), `port`, `proto`, and matchers
  `host` / `cidr` / `local_cidr` / `groups` / `ca_name` / `ca_sha`. Rules are
  per-instance.
- `ca_sha` references a CA fingerprint; `checkCaReferences` validates it on
  save. `groups` renders as a YAML list (a single group still renders as a
  one-element list).
- Group matchers use the chip widget in the grid.
- This is overlay policy only; it has nothing to do with `nebula_firewall()` (the
  OPNsense `_firewall` hook that opens the UDP listen port) or the OPNsense
  firewall tabs.

## Live test strategy

- Model/render: `phpunit --filter 'FirewallRuleCRUDTest|GenerateConfigTest'` —
  GenerateConfigTest pins per-direction rendering, the permissive fallback, and
  group-list rendering.
- Browser: add inbound + outbound rules (and clear one direction to see the
  fallback), Apply, confirm the emitted `firewall:` block and `nebula -test`.
