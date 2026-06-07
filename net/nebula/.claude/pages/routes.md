# Page: Routes (`/ui/nebula/routes`)

Three related per-instance route tables in one page: the lighthouse **static host
map**, **unsafe routes** (route non-Nebula subnets over the overlay), and **tun
routes** (per-route MTU overrides).

## Files

- Models: `Nebula.xml` → the static-host-map, `unsafe_routes.route`, and
  `tun_routes.route` arrays. Rendered by `Nebula.php::generateConfig`
  (`static_host_map`, `tun.unsafe_routes`, `tun.routes`).
- Page controller: `controllers/OPNsense/Nebula/RoutesController.php`.
- API controllers: `Api/StaticHostMapController.php`, `Api/UnsafeRouteController.php`,
  `Api/TunRouteController.php`.
- Forms: `forms/dialogStaticHostMap.xml`, `dialogUnsafeRoute.xml`,
  `dialogTunRoute.xml`.
- View: `views/OPNsense/Nebula/routes.volt` (one grid per table, each filtered by
  instance).

## Maintenance notes

- All three are **per-instance** (an `instance` field); each grid filters by the
  selected instance and renders only that instance's rows into its config.
- **Static host map**: maps a Nebula IP → underlay `host:port` entries (how a node
  finds a lighthouse / fixed peer). Goes into the nebula `static_host_map` block.
- **Unsafe routes** (`tun.unsafe_routes`): advertise/route a non-Nebula subnet
  over the overlay — `route`, `via` (a Nebula IP), `install` (add to the system
  route table), optional MTU/metric.
- **Tun routes** (`tun.routes`): per-overlay-route MTU override (`route` + `mtu`).
- These only affect the rendered config; changes apply on Apply (HUP reload, no
  tunnel drop). After editing the rendering in `generateConfig`, re-check
  `nebula -test`.

## Live test strategy

- Model/render: the relevant assertions live in `GenerateConfigTest` (the
  `tun.unsafe_routes` / `tun.routes` / static-map blocks). Add cases there when
  changing rendering.
- Browser: add a route in each table, Apply, and confirm it lands in the rendered
  instance config and passes `nebula -test`; for unsafe routes with `install`,
  confirm the system route appears.
