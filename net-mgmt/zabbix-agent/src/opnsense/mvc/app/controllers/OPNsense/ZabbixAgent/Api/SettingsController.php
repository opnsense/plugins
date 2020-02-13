<?php

/**
 *    Copyright (C) 2017 Frank Wall
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

namespace OPNsense\ZabbixAgent\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Config;
use OPNsense\ZabbixAgent\ZabbixAgent;

/**
 * Class SettingsController
 * @package OPNsense\ZabbixAgent
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'zabbixagent';
    protected static $internalModelClass = '\OPNsense\ZabbixAgent\ZabbixAgent';

    public function searchUserparametersAction()
    {
        return $this->searchBase('userparameters.userparameter', array("enabled", "key", "command"));
    }

    public function getUserparameterAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('userparameter', 'userparameters.userparameter', $uuid);
    }

    public function addUserparameterAction()
    {
        return $this->addBase('userparameter', 'userparameters.userparameter');
    }

    public function delUserparameterAction($uuid)
    {
        return $this->delBase('userparameters.userparameter', $uuid);
    }

    public function setUserparameterAction($uuid)
    {
        return $this->setBase('userparameter', 'userparameters.userparameter', $uuid);
    }

    public function toggleUserparameterAction($uuid)
    {
        return $this->toggleBase('userparameters.userparameter', $uuid);
    }

    public function searchAliasesAction()
    {
        return $this->searchBase('aliases.alias', array("enabled", "key", "sourceKey"));
    }

    public function getAliasAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('alias', 'aliases.alias', $uuid);
    }

    public function addAliasAction()
    {
        return $this->addBase('alias', 'aliases.alias');
    }

    public function delAliasAction($uuid)
    {
        return $this->delBase('aliases.alias', $uuid);
    }

    public function setAliasAction($uuid)
    {
        return $this->setBase('alias', 'aliases.alias', $uuid);
    }

    public function toggleAliasAction($uuid)
    {
        return $this->toggleBase('aliases.alias', $uuid);
    }
}
