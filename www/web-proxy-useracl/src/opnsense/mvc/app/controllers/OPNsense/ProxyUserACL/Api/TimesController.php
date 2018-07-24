<?php

/**
 *    Copyright (C) 2017-2018 Smart-Soft
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
 * Class SettingsController Handles settings related API actions for the ProxyUserACL
 * @package OPNsense\ProxyUserACL
 */
class TimesController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Time';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search time rule
     * @return array
     * @throws \ReflectionException
     */
    public function searchTimeAction()
    {
        return $this->searchBase(
            "general.Times.Time",
            array('Names', 'uuid'),
            "Names"
        );
    }

    /**
     * retrieve time rule settings or return defaults
     * @param null $uuid
     * @return array
     * @throws \ReflectionException
     */
    public function getTimeAction($uuid = null)
    {
        return $this->getBase("Time", "general.Times.Time", $uuid);
    }

    /**
     * update time rule item
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function setTimeAction($uuid)
    {
        return $this->setBase("Time", "general.Times.Time", $uuid);
    }

    /**
     * add new time rule and set with attributes from post
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function addTimeAction()
    {
        return $this->addBase("Time", "general.Times.Time");
    }

    /**
     * delete time rule by uuid
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delTimeAction($uuid)
    {
        foreach ($this->getModel()->general->ACLs->ACL->getChildren() as $acl) {
            if (($times = $acl->Times) != null && isset($times->getNodeData()[$uuid]["selected"]) && $times->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        foreach ($this->getModel()->general->HTTPAccesses->HTTPAccess->getChildren() as $acl) {
            if (($times = $acl->Times) != null && isset($times->getNodeData()[$uuid]["selected"]) && $times->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        foreach ($this->getModel()->general->ICAPs->ICAP->getChildren() as $acl) {
            if (($times = $acl->Times) != null && isset($times->getNodeData()[$uuid]["selected"]) && $times->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        return $this->delBase("general.Times.Time", $uuid);
    }

}
