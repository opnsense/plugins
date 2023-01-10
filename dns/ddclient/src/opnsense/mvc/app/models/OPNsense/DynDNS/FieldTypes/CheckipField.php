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

class CheckipField extends BaseListField
{
    private static $internalCacheOptionList = [];

    public function setOptionValues($data)
    {
        if (!empty(self::$internalCacheOptionList)) {
            $this->internalOptionList = self::$internalCacheOptionList;
            return;
        }
        if (is_array($data)) {
            $opn_backend = (string)$this->getParentModel()->general->backend == 'opnsense';
            foreach ($data as $key => $value) {
                self::$internalCacheOptionList[$key] = gettext($value);
            }
            if ($opn_backend) {
                // OPNsense backend, change interface label and add IPv6 option
                self::$internalCacheOptionList['if'] = gettext("Interface [IPv4]");
                self::$internalCacheOptionList['if6'] = gettext("Interface [IPv6]");
            }
            $this->internalOptionList = self::$internalCacheOptionList;
        }
    }
}
