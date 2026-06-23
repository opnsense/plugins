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

namespace Deciso\Proxy\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Config;

/**
 * Class UserGroupField
 */
class UserGroupField extends BaseListField
{
    /**
     * @var array collected options
     */
    private static $internalCacheOptionList = array();

    /**
     * @return string identifying selected options
     */
    private function optionSetId()
    {
        return "0";
    }

    /**
     * generate validation data (list of countries)
     */
    protected function actionPostLoadingEvent()
    {
        $setid = $this->optionSetId();
        if (!isset(self::$internalCacheOptionList[$setid])) {
            self::$internalCacheOptionList[$setid] =  array();
        }
        if (empty(self::$internalCacheOptionList[$setid])) {
            $cnf = Config::getInstance()->object();
            foreach (['group', 'user'] as $topic) {
                if (!empty($cnf->system->$topic)) {
                    foreach ($cnf->system->$topic as $node) {
                        $prefix = $topic == "user" ? "*" : "";
                        $tp = $topic == "user" ? "u" : "g";
                        self::$internalCacheOptionList[$setid][$tp . ":" . $node->name] = $prefix . $node->name;
                    }
                }
            }
            ksort(self::$internalCacheOptionList[$setid]);
        }
        $this->internalOptionList = self::$internalCacheOptionList[$setid];
    }
}
