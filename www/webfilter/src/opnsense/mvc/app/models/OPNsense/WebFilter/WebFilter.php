<?php

/**
 *    Copyright (C) 2018-2020 Cloudfence - Julio Camargo
 *    Copyright (c) 2019 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\WebFilter;

use OPNsense\Base\BaseModel;

/**
 * Class WebFilter
 * @package OPNsense\WebFilter
 */
class WebFilter extends BaseModel
{
    
    /**
     * mark configuration as changed when data is pushed back to the config
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        @touch("/tmp/webfilter.dirty");
        return parent::serializeToConfig($validateFullModel, $disable_validation);
    }
    /**
     * get configuration state
     * @return bool
     */
    public function configChanged()
    {
        return file_exists("/tmp/webfilter.dirty");
    }
    /**
     * mark configuration as consistent with the running config
     * @return bool
     */
    public function configClean()
    {
        return @unlink("/tmp/webfilter.dirty");
    }


    /**
     * retrieve rule by number
     * @param $uuid rule number
     * @return null|BaseField rule details
     */
    public function getByRuleID($uuid)
    {
        foreach ($this->rules->rule->iterateItems() as $rule) {
            if ((string)$uuid === (string)$rule->getAttributes()["uuid"]) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * create a new rule
     * @param string $name
     * @param string $source
     * @param string $destination
     * @param string $description
     * @param string $enabled default add disabled rules, if triggered enabled be sure to call regenerate config file.
     * @return string
     */
    
    public function newRule($name, $source, $destination, $description = "", $enabled = "0", $parameters = array())
    {
        $rule = $this->rules->rule->Add();
        $uuid = $rule->getAttributes()['uuid'];
        $rule->name = $name;
        $rule->source = $source;
        $rule->destination = $destination;
        $rule->description = $description;
        $rule->enabled = $enabled;
        foreach ($parameters as $key => $value) {
            $rule->$key = $value;
        }
        return $uuid;
    }
}
