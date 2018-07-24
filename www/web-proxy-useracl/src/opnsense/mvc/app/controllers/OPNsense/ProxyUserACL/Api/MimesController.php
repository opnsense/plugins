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
class MimesController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Mime';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search MIME types
     * @return array
     * @throws \ReflectionException
     */
    public function searchMimeAction()
    {
        return $this->searchBase(
            "general.Mimes.Mime",
            array('Names', 'uuid'),
            "Names"
        );
    }

    /**
     * retrieve MIME types settings or return defaults
     * @param null $uuid
     * @return array
     * @throws \ReflectionException
     */
    public function getMimeAction($uuid = null)
    {
        return $this->getBase("Mime", "general.Mimes.Mime", $uuid);
    }

    /**
     * update MIME types item
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function setMimeAction($uuid)
    {
        return $this->setBase("Mime", "general.Mimes.Mime", $uuid);
    }

    /**
     * add new MIME type and set with attributes from post
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function addMimeAction()
    {
        return $this->addBase("Mime", "general.Mimes.Mime");
    }

    /**
     * delete MIME type by uuid
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delMimeAction($uuid)
    {
        foreach ($this->getModel()->general->ACLs->ACL->getChildren() as $acl) {
            if (($mimess = $acl->Mimes) != null && isset($mimess->getNodeData()[$uuid]["selected"]) && $mimess->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        foreach ($this->getModel()->general->HTTPAccesses->HTTPAccess->getChildren() as $acl) {
            if (($mimess = $acl->Mimes) != null && isset($mimess->getNodeData()[$uuid]["selected"]) && $mimess->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        foreach ($this->getModel()->general->ICAPs->ICAP->getChildren() as $acl) {
            if (($mimess = $acl->Mimes) != null && isset($mimess->getNodeData()[$uuid]["selected"]) && $mimess->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        return $this->delBase("general.Mimes.Mime", $uuid);
    }

}
