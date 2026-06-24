<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Nebula\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Firewall\Util;

/**
 * Tun-route API — per-instance per-route MTU overrides for Nebula overlay routes.
 *
 * Each entry targets one instance and renders into that instance's tun.routes
 * list as {route, mtu}. These are overlay routes (the node's own subnets), not
 * unsafe routes — see UnsafeRouteController for routing non-Nebula subnets.
 *
 * Endpoints:
 *   GET  /api/nebula/tun_route/search_item[?instance=<uuid>]
 *   GET  /api/nebula/tun_route/get_item/<uuid>
 *   POST /api/nebula/tun_route/add_item
 *   POST /api/nebula/tun_route/set_item/<uuid>
 *   POST /api/nebula/tun_route/del_item/<uuid>
 *   POST /api/nebula/tun_route/toggle_item/<uuid>[/<enabled>]
 *
 * @package OPNsense\Nebula\Api
 */
class TunRouteController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    public function searchItemAction()
    {
        $instanceUuid = trim($this->request->get('instance', 'string', ''));

        $filter_funct = null;
        if ($instanceUuid !== '') {
            $filter_funct = function ($record) use ($instanceUuid) {
                return (string)$record->instance === $instanceUuid;
            };
        }

        $result = $this->searchBase(
            'tun_routes.route',
            ['enabled', 'instance', 'route', 'mtu', 'description'],
            null,
            $filter_funct
        );

        if (!empty($result['rows']) && is_array($result['rows'])) {
            $instByUuid = [];
            foreach ($this->getModel()->instances->instance->iterateItems() as $inst) {
                $instByUuid[$inst->getAttribute('uuid')] = (string)$inst->description;
            }
            foreach ($result['rows'] as &$row) {
                $u = trim((string)($row['instance'] ?? ''));
                $row['instance'] = array_key_exists($u, $instByUuid)
                    ? $instByUuid[$u] : '(deleted)';
            }
            unset($row);
        }
        return $result;
    }

    public function getItemAction($uuid = null)
    {
        // Use the "tunroute" wrapper key (not "route") so this dialog's field ids
        // (tunroute.*) don't collide with the unsafe-route dialog (route.*) — both
        // base_dialogs render on the same Routes page, so duplicate DOM ids would
        // break form serialization and suppress the save/apply alert.
        return $this->getBase('tunroute', 'tun_routes.route', $uuid);
    }

    /**
     * Is $route (a CIDR) contained within one of the cert's $networks? Mirrors
     * nebula's rule (overlay/route.go): the route's address must be inside a
     * network AND the route must be at least as specific (bits >= network bits).
     */
    private function routeWithinNetworks(string $route, array $networks): bool
    {
        if (strpos($route, '/') === false) {
            return false;
        }
        [$addr, $bits] = explode('/', $route, 2);
        foreach ($networks as $net) {
            $net = trim($net);
            if ($net === '' || strpos($net, '/') === false) {
                continue;
            }
            [, $netBits] = explode('/', $net, 2);
            if (Util::isIPInCIDR($addr, $net) && (int)$bits >= (int)$netBits) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reject a tun.route whose CIDR is not inside the instance certificate's VPN
     * networks. Nebula enforces this only at daemon start (NOT in `nebula -test`),
     * so without this check the GUI would accept a route that then fails to start
     * with "Could not parse tun.routes ... not contained within the configured
     * vpn networks". Unsafe routes are exempt — those are for external subnets.
     *
     * @return array|null  null = valid; array = ['result'=>'failed', ...]
     */
    private function checkRouteContainment(): ?array
    {
        $entry = $this->request->getPost('tunroute');
        if (!is_array($entry)) {
            $entry = [];
        }
        $route = isset($entry['route']) ? trim((string)$entry['route']) : '';
        $instUuid = isset($entry['instance']) ? trim((string)$entry['instance']) : '';
        if ($route === '' || $instUuid === '') {
            return null; // required-ness handled by the model validators
        }

        // instance -> certref -> certificate -> networks
        $mdl = $this->getModel();
        $certref = '';
        foreach ($mdl->instances->instance->iterateItems() as $inst) {
            if ($inst->getAttribute('uuid') === $instUuid) {
                $certref = (string)$inst->certref;
                break;
            }
        }
        $networks = [];
        if ($certref !== '') {
            foreach ($mdl->pki->certificates->certificate->iterateItems() as $crt) {
                if ($crt->getAttribute('uuid') === $certref) {
                    $networks = array_values(array_filter(
                        array_map('trim', explode(',', (string)$crt->networks)),
                        fn($s) => $s !== ''
                    ));
                    break;
                }
            }
        }
        if (empty($networks)) {
            // No cert/networks to validate against (e.g. instance has no cert yet);
            // let nebula surface any issue rather than block on incomplete data.
            return null;
        }
        if (!$this->routeWithinNetworks($route, $networks)) {
            return [
                'result'      => 'failed',
                'validations' => [
                    'tunroute.route' => sprintf(
                        'Route %s must be inside this instance\'s certificate network(s): %s. ' .
                        'tun.routes only set the MTU for overlay routes — use Unsafe Routes for external subnets.',
                        $route,
                        implode(', ', $networks)
                    ),
                ],
            ];
        }
        return null;
    }

    public function addItemAction()
    {
        $err = $this->checkRouteContainment();
        if ($err !== null) {
            return $err;
        }
        return $this->addBase('tunroute', 'tun_routes.route');
    }

    public function setItemAction($uuid = null)
    {
        $err = $this->checkRouteContainment();
        if ($err !== null) {
            return $err;
        }
        return $this->setBase('tunroute', 'tun_routes.route', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        return $this->delBase('tun_routes.route', $uuid);
    }

    public function toggleItemAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('tun_routes.route', $uuid, $enabled);
    }
}
