<?php

/**
 *    Copyright (C) 2017-2019 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\AcmeClient\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\AcmeClient\AcmeClient;

/**
 * Class AccountsController
 * @package OPNsense\AcmeClient
 */
class AccountsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'acmeclient';
    protected static $internalModelClass = '\OPNsense\AcmeClient\AcmeClient';

    public function getAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('account', 'accounts.account', $uuid);
    }

    public function addAction()
    {
        return $this->addBase('account', 'accounts.account');
    }

    public function updateAction($uuid)
    {
        return $this->setBase('account', 'accounts.account', $uuid);
    }

    public function delAction($uuid)
    {
        return $this->delBase('accounts.account', $uuid);
    }

    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('accounts.account', $uuid);
    }

    public function searchAction()
    {
        return $this->searchBase('accounts.account', array('enabled', 'name', 'email', 'ca', 'statusCode', 'statusLastUpdate'), 'name');
    }

    /**
     * register account by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function registerAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlAcme = new AcmeClient();

            if ($uuid != null) {
                $node = $mdlAcme->getNodeByReference('accounts.account.' . $uuid);
                if ($node != null) {
                    $backend = new Backend();
                    $response = $backend->configdRun("acmeclient register-account {$uuid}");
                    return array("response" => $response);
                }
            }
        }
        return $result;
    }
}
