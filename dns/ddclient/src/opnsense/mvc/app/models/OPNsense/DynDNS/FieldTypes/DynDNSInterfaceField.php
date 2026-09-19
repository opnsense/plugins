<?php

/*
 * Copyright (C) 2026 Claudio Guareschi
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

use OPNsense\Base\FieldTypes\InterfaceField;
use OPNsense\Core\Config;

class DynDNSInterfaceField extends InterfaceField
{
    protected function actionPostLoadingEvent()
    {
        parent::actionPostLoadingEvent();

        $virtualIPs = [];
        $configObj = Config::getInstance()->object();
        if (!empty($configObj->virtualip) && !empty($configObj->virtualip->vip)) {
            foreach ($configObj->virtualip->vip as $vip) {
                $attributes = $vip->attributes();
                $uuid = isset($attributes['uuid']) ? (string)$attributes['uuid'] : '';
                $address = (string)$vip->subnet;
                if ((string)$vip->mode != 'carp' || empty($uuid)) {
                    continue;
                } elseif (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $family = 4;
                } elseif (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                    $family = 6;
                } else {
                    continue;
                }
                $virtualIPs["vip:{$family}:{$uuid}"] = (string)$vip->descr;
            }
        }
        natcasesort($virtualIPs);
        $this->internalOptionList = array_merge($this->internalOptionList, $virtualIPs);
    }
}
