<?php

/*
 * Copyright (C) 2015-2019 Deciso B.V.
 * Copyright (C) 2024 Cedrik Pischem
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

namespace Pischem\Caddy\FieldTypes;

use OPNsense\Core\Config;
use OPNsense\Base\FieldTypes\BaseListField;

/**
 * This is forked from the Base InterfaceField. 
 * It provides the descriptive names of interfaces in the UI, but saves the physical interface names.
 */

class RealInterfaceField extends BaseListField
{
    /**
     * @var array Collected options
     */
    private static $internalStaticOptionList = array();

    /**
     * @var string Key to use for option selections, to prevent excessive reloading
     */
    private $internalCacheKey = '*';

    /**
     * Collect parents for LAGG interfaces
     * @return array Named array containing device and LAGG interface
     */
    private function getConfigLaggInterfaces()
    {
        $physicalInterfaces = array();
        $configObj = Config::getInstance()->object();
        if (!empty($configObj->laggs)) {
            foreach ($configObj->laggs->children() as $key => $lagg) {
                if (!empty($lagg->members)) {
                    foreach (explode(',', $lagg->members) as $interface) {
                        if (!isset($physicalInterfaces[$interface])) {
                            $physicalInterfaces[$interface] = array();
                        }
                        $physicalInterfaces[$interface][] = (string)$lagg->laggif;
                    }
                }
            }
        }
        return $physicalInterfaces;
    }

    /**
     * Collect parents for VLAN interfaces
     * @return array Named array containing device and VLAN interfaces
     */
    private function getConfigVLANInterfaces()
    {
        $physicalInterfaces = array();
        $configObj = Config::getInstance()->object();
        if (!empty($configObj->vlans)) {
            foreach ($configObj->vlans->children() as $key => $vlan) {
                if (!isset($physicalInterfaces[(string)$vlan->if])) {
                    $physicalInterfaces[(string)$vlan->if] = array();
                }
                $physicalInterfaces[(string)$vlan->if][] = (string)$vlan->vlanif;
            }
        }
        return $physicalInterfaces;
    }

    /**
     * Generate validation data (list of interfaces)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList[$this->internalCacheKey])) {
            self::$internalStaticOptionList[$this->internalCacheKey] = array();

            // Explicitly add a 'None' option at the beginning of the list
            self::$internalStaticOptionList[$this->internalCacheKey][''] = "None";

            $configObj = Config::getInstance()->object();
            if (isset($configObj->interfaces)) {
                foreach ($configObj->interfaces->children() as $key => $value) {
                    if (!empty($value->if)) {
                        // Key is the real interface name, Value is the descriptive name
                        self::$internalStaticOptionList[$this->internalCacheKey][(string)$value->if] = (string)$key;
                    }
                }
            }

            // Collect parents for lagg/vlan interfaces
            $physicalInterfaces = array_merge($this->getConfigLaggInterfaces(), $this->getConfigVLANInterfaces());

            // Add unique devices
            foreach ($physicalInterfaces as $interface => $devices) {
                if (empty(self::$internalStaticOptionList[$this->internalCacheKey][$interface])) {
                    self::$internalStaticOptionList[$this->internalCacheKey][$interface] = $interface;
                }
            }

            natcasesort(self::$internalStaticOptionList[$this->internalCacheKey]);
        }
        $this->internalOptionList = self::$internalStaticOptionList[$this->internalCacheKey];
    }

    private function updateInternalCacheKey()
    {
        $this->internalCacheKey = md5(serialize(array()));
    }
}
