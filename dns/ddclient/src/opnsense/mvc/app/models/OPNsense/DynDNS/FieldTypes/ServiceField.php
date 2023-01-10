<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\DynDNS\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Backend;

class ServiceField extends BaseListField
{
    private static $internalCacheOptionList = [];

    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalCacheOptionList)) {
            // request supported services from backend
            if ((string)$this->getParentModel()->general->backend == 'opnsense') {
                $supported = json_decode((new Backend())->configdRun("ddclient opnbackend supported"), true);
                if (!empty($supported)) {
                    foreach ($supported as $srv) {
                        self::$internalCacheOptionList[$srv] = $srv;
                    }
                }
            }
        }
        $this->internalOptionList = self::$internalCacheOptionList;
    }

    /**
     * setter for option values
     * @param $data
     */
    public function setOptionValues($data)
    {
        if (!empty(self::$internalCacheOptionList) || (string)$this->getParentModel()->general->backend == 'opnsense') {
            return;
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                self::$internalCacheOptionList[$key] = gettext($value);
            }
            $this->internalOptionList = self::$internalCacheOptionList;
        }
    }
}
