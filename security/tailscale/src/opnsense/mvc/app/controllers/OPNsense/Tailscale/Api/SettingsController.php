<?php

/*
 * Copyright (C) 2024 Sheridan Computers
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

namespace OPNsense\Tailscale\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'settings';
    protected static $internalModelClass = '\OPNsense\Tailscale\Settings';

    public function searchSubnetAction()
    {
        return $this->searchBase("subnets.subnet4", null, "subnet");
    }
    public function setSubnetAction($uuid)
    {
        return $this->setBase("subnet4", "subnets.subnet4", $uuid);
    }
    public function addSubnetAction()
    {
        return $this->addBase("subnet4", "subnets.subnet4");
    }

    public function getSubnetAction($uuid = null)
    {
        return $this->getBase("subnet4", "subnets.subnet4", $uuid);
    }

    public function delSubnetAction($uuid)
    {
        return $this->delBase("subnets.subnet4", $uuid);
    }

    public function reloadAction()
    {
        $mdl = $this->getModel();
        $enabled = $mdl->enabled->__toString() === '1';
        $response = $this->toggleTailScaleService($enabled);
        return ['result ' => $response];
    }

    private function toggleTailscaleService($enabled)
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/Tailscale');
        $action = $enabled ? 'start' : 'stop';
        return trim($backend->configdRun('tailscale ' . $action));
    }
}
