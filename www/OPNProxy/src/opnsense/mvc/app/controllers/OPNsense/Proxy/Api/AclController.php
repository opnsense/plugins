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


namespace OPNsense\Proxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class AclController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'proxy';
    protected static $internalModelClass = 'Deciso\Proxy\ACL';

    public function searchPolicyAction()
    {
        return $this->searchBase("policies.policy", array('enabled', 'description', 'action'), "description");
    }

    public function setPolicyAction($uuid)
    {
        return $this->setBase("policy", "policies.policy", $uuid);
    }

    public function addPolicyAction()
    {
        return $this->addBase("policy", "policies.policy");
    }

    public function getPolicyAction($uuid = null)
    {
        return $this->getBase("policy", "policies.policy", $uuid);
    }

    public function delPolicyAction($uuid)
    {
        return $this->delBase("policies.policy", $uuid);
    }

    public function togglePolicyAction($uuid, $enabled = null)
    {
        return $this->toggleBase("policies.policy", $uuid, $enabled);
    }
    public function searchCustomPolicyAction()
    {
        return $this->searchBase("custom_policies.policy", array('enabled', 'description', 'action'), "description");
    }

    public function setCustomPolicyAction($uuid)
    {
        return $this->setBase("custom_policy", "custom_policies.policy", $uuid);
    }

    public function addCustomPolicyAction()
    {
        return $this->addBase("custom_policy", "custom_policies.policy");
    }

    public function getCustomPolicyAction($uuid = null)
    {
        return $this->getBase("custom_policy", "custom_policies.policy", $uuid);
    }

    public function delCustomPolicyAction($uuid)
    {
        return $this->delBase("custom_policies.policy", $uuid);
    }

    public function toggleCustomPolicyAction($uuid, $enabled = null)
    {
        return $this->toggleBase("custom_policies.policy", $uuid, $enabled);
    }

    public function applyAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $backend = new Backend();
            $backend->configdRun('template reload Deciso/Proxy');
            $backend->configdRun('opnproxy sync_users');
            return array("status" => trim($backend->configdRun('opnproxy apply_policies')));
        } else {
            return array("status" => "error");
        }
    }

    public function testAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('uri')) {
            $src = $this->request->getPost('src', 'striptags', '');
            $src = !empty($src) ? $src : "-";
            $user = $this->request->getPost('user', null, '');
            $user = !empty($user) ? $user : "-";
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdpRun('opnproxy user test', [
                $user, $this->request->getPost('uri'), $src
            ]);
            $respose = json_decode($response, true);
            if (!empty($response)) {
                return $respose;
            }
        }
        return array("status" => "error");
    }
}
