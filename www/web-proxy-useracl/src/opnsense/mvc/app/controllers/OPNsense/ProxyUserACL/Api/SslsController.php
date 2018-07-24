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
use \OPNsense\Base\UIModelGrid;
use \OPNsense\ProxyUserACL\ProxyUserACL;

/**
 * Class SettingsController Handles settings related API actions for the ProxyUserACL
 * @package OPNsense\ProxyUserACL
 */
class SslsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Arp';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search SSL Bump rule
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    public function searchAclAction()
    {
        $this->sessionClose();

        $mdlProxyUserACL = new ProxyUserACL();
        $grid = new UIModelGrid($mdlProxyUserACL->general->SSLs->SSL);
        $columns = ["Users", "Domains", "Agents", "Times", "Mimes", "Srcs", "Dsts", "Arps"];
        $ret = $grid->fetchBindRequest($this->request, array_merge($columns, ["uuid"]),
            "Users");
        foreach ($ret["rows"] as &$row) {
            $visible = [];
            foreach ($columns as $column) {
                if ($row[$column] != "") {
                    $visible[] = $row[$column];
                }
            }

            $row["Visible"] = implode("|", $visible);
        }
        return $ret;
    }

    /**
     * retrieve SSL Bump rule settings or return defaults
     * @param null $uuid
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    public function getACLAction($uuid = null)
    {
        $mdlProxyUserACL = new ProxyUserACL();
        if ($uuid == null) {
            // generate new node, but don't save to disc
            $node = $mdlProxyUserACL->general->SSLs->SSL->add();
            $nodes = $node->getNodes();
            return ["SSL" => $nodes];
        }

        $node = $mdlProxyUserACL->getNodeByReference('general.SSLs.SSL.' . $uuid);
        if ($node != null) {
            // return node
            $nodes = $node->getNodes();
            return ["SSL" => $nodes];
        }

        return [];
    }

    /**
     * update SSL Bump rule item
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function setAclAction($uuid)
    {
        return $this->setBase("SSL", "general.SSLs.SSL", $uuid);
    }

    /**
     * add new SSL Bump rule and set with attributes from post
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function addAclAction()
    {
        return $this->addBase("SSL", "general.SSLs.SSL");
    }

    /**
     * delete SSL Bump rule by uuid
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delAclAction($uuid)
    {
        return $this->delBase("general.SSLs.SSL", $uuid);
    }

}
