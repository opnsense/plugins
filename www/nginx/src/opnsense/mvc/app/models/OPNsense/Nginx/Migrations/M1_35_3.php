<?php

/*
 * Copyright (C) 2026 muchachagrande
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

namespace OPNsense\Nginx\Migrations;

use OPNsense\Base\BaseModelMigration;

class M1_35_3 extends BaseModelMigration
{
    // Purge sni_hostname_upstream_map_item / ip_acl_item entries left
    // orphaned by the bug fixed in #5650.
    public function run($model)
    {
        $this->purgeOrphans($model, 'sni_hostname_upstream_map', 'sni_hostname_upstream_map_item');
        $this->purgeOrphans($model, 'ip_acl', 'ip_acl_item');

        // run default migration actions
        parent::run($model);
    }

    private function purgeOrphans($model, $parent_path, $item_path)
    {
        $referenced = [];
        $parents = $model->getNodeByReference($parent_path);
        if ($parents != null) {
            foreach ($parents->iterateItems() as $parent) {
                foreach (explode(',', (string)$parent->data) as $uuid) {
                    if ($uuid !== '') {
                        $referenced[$uuid] = true;
                    }
                }
            }
        }
        $items = $model->getNodeByReference($item_path);
        if ($items != null) {
            // Collect the orphan uuids first -- do not mutate the collection
            // while iterating it.
            $orphans = [];
            foreach ($items->iterateItems() as $uuid => $item) {
                if (!isset($referenced[$uuid])) {
                    $orphans[] = $uuid;
                }
            }
            foreach ($orphans as $uuid) {
                $items->del($uuid);
            }
        }
    }
}
