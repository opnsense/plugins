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
 * Static-host-map API — per-instance "how to reach a peer before discovery"
 * entries, mapping a peer's Nebula overlay IP to one or more underlay
 * host:port addresses.
 *
 * Each entry targets one instance and is rendered into that instance's
 * static_host_map map-of-lists (unioned with the legacy free-text field).
 *
 * Endpoints:
 *   GET  /api/nebula/static_host_map/search_item[?instance=<uuid>]
 *   GET  /api/nebula/static_host_map/get_item/<uuid>
 *   POST /api/nebula/static_host_map/add_item
 *   POST /api/nebula/static_host_map/set_item/<uuid>
 *   POST /api/nebula/static_host_map/del_item/<uuid>
 *   POST /api/nebula/static_host_map/toggle_item/<uuid>[/<enabled>]
 *
 * @package OPNsense\Nebula\Api
 */
class StaticHostMapController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    /**
     * Search entries, optionally scoped to one instance (?instance=<uuid>).
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
            'static_hostmap.entry',
            ['enabled', 'instance', 'nebula_ip', 'addresses', 'description'],
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
        return $this->getBase('entry', 'static_hostmap.entry', $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase('entry', 'static_hostmap.entry');
    }

    public function setItemAction($uuid = null)
    {
        return $this->setBase('entry', 'static_hostmap.entry', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        return $this->delBase('static_hostmap.entry', $uuid);
    }

    public function toggleItemAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('static_hostmap.entry', $uuid, $enabled);
    }
}
