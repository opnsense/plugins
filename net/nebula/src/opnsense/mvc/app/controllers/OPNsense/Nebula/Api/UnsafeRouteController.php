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

/**
 * Unsafe-routes API — per-instance routes for non-Nebula subnets reached over
 * the overlay via a Nebula peer.
 *
 * Each route targets one instance and is rendered into that instance's
 * tun.unsafe_routes list as {route, via, [mtu], [metric], install}. The peer
 * named by `via` must carry the route as a subnet in its certificate.
 *
 * Endpoints:
 *   GET  /api/nebula/unsafe_route/search_item[?instance=<uuid>]
 *   GET  /api/nebula/unsafe_route/get_item/<uuid>
 *   POST /api/nebula/unsafe_route/add_item
 *   POST /api/nebula/unsafe_route/set_item/<uuid>
 *   POST /api/nebula/unsafe_route/del_item/<uuid>
 *   POST /api/nebula/unsafe_route/toggle_item/<uuid>[/<enabled>]
 *
 * @package OPNsense\Nebula\Api
 */
class UnsafeRouteController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    /**
     * Search routes, optionally scoped to one instance (?instance=<uuid>).
     * Resolves the instance uuid to its description for the grid.
     */
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
            'unsafe_routes.route',
            ['enabled', 'instance', 'route', 'via', 'mtu', 'metric', 'install', 'description'],
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
        return $this->getBase('route', 'unsafe_routes.route', $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase('route', 'unsafe_routes.route');
    }

    public function setItemAction($uuid = null)
    {
        return $this->setBase('route', 'unsafe_routes.route', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        return $this->delBase('unsafe_routes.route', $uuid);
    }

    public function toggleItemAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('unsafe_routes.route', $uuid, $enabled);
    }
}
