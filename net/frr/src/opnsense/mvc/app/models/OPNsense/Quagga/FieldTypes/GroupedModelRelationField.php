<?php

/*
 * Copyright (C) 2026 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the disclaimer in the documentation
 *    and/or other materials provided with the distribution.
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

namespace OPNsense\Quagga\FieldTypes;

use OPNsense\Base\FieldTypes\ModelRelationField;

/**
 * the plugin stores every list entry as a separate record while references logically
 * target all records with the same displayed key. Restructuring this legacy
 * data would require fragile migrations, so expose one relation option per
 * key while retaining an entry UUID as the stored reference.
 */
class GroupedModelRelationField extends ModelRelationField
{
    public function getNodeData()
    {
        $options = parent::getNodeData();
        $result = [];
        $groups = [];
        foreach ($options as $uuid => $option) {
            if ($uuid === '') {
                $result[$uuid] = $option;
                continue;
            }

            $group = $option['value'];
            if (isset($groups[$group])) {
                if (!empty($option['selected'])) {
                    $result[$groups[$group]]['selected'] = 1;
                }
                continue;
            }
            $groups[$group] = $uuid;
            $result[$uuid] = $option;
        }

        return $result;
    }
}
