<?php

/**
 *    Copyright (C) 2019 Smart-Soft
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

namespace OPNsense\ProxyUserACL\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;


/**
 * Class MacsController
 * @package OPNsense\ProxyUserACL\Api
 */
class MacsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'MAC';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search MACs list
     * @return array result
     * @throws \ReflectionException
     */
    public function searchMacAction()
    {
        return $this->searchBase(
            "general.MACs.MAC",
            ['Description', 'uuid'],
            "Description"
        );
    }

    /**
     * retrieve MAC settings or return defaults
     * @param string $uuid item unique id
     * @return array result
     * @throws \ReflectionException
     */
    public function getMacAction($uuid = null)
    {
        return $this->getBase("MAC", "general.MACs.MAC", $uuid);
    }

    /**
     * update MAC item
     * @param string $uuid item unique id
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function setMacAction($uuid)
    {
        return $this->setBase("MAC", "general.MACs.MAC", $uuid);
    }

    /**
     * add new MAC and set with attributes from post
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function addMacAction()
    {
        return $this->addBase("MAC", "general.MACs.MAC");
    }

    /**
     * delete agent by uuid
     * @param string $uuid item unique id
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delMacAction($uuid)
    {
        foreach ($this->getModel()->general->HTTPAccesses->HTTPAccess->getChildren() as $acl) {
            if (($MACs = $acl->MACs) != null && isset($MACs->getNodeData()[$uuid]["selected"]) && $MACs->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => gettext("value is used")];
            }
        }
        return $this->delBase("general.MACs.MAC", $uuid);
    }

}
