<?php

/*
 * Copyright (C) 2017 David Harrigan
 * Copyright (C) 2017 Deciso B.V.
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

namespace OPNsense\Zerotier\Api;

require_once 'plugins.inc.d/zerotier.inc';

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Zerotier\Zerotier;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'Zerotier';
    protected static $internalModelClass = '\OPNsense\Zerotier\Zerotier';

    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlZerotier = $this->getModel();
            if (empty($mdlZerotier->localconf->__toString())) {
                $mdlZerotier->localconf = '{}';
            }
            $result = array("zerotier" => $mdlZerotier->getNodes());
        }
        return $result;
    }

    public function setAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("zerotier")) {
            $mdlZerotier = $this->getModel();
            $mdlZerotier->setNodes($this->request->getPost("zerotier"));
            $mdlZerotier->serializeToConfig();
            Config::getInstance()->save();
            $enabled = isEnabled($mdlZerotier);
            $result["result"] = $this->toggleZerotierService($enabled);
        }
        return $result;
    }

    public function statusAction()
    {
        $mdlZerotier = $this->getModel();
        $enabled = isEnabled($mdlZerotier);

        $response = trim((new Backend())->configdRun('zerotier status'));

        if (strpos($response, "not running") > 0) {
            if (isEnabled($mdlZerotier)) {
                $status = "stopped";
            } else {
                $status = "disabled";
            }
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } elseif (!$enabled) {
            $status = "disabled";
        } else {
            $status = "unknown";
        }

        return array("result" => $status);
    }

    private function toggleZerotierService($enabled)
    {
        $backend = new Backend();
        $backend->configdRun("template reload OPNsense/zerotier");
        $action = $enabled ? "start" : "stop";
        return trim($backend->configdRun("zerotier $action"));
    }
}
