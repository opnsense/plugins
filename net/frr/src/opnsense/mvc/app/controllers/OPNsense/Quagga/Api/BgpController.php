<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
 * Copyright (C) 2017 Fabian Franz
 * Copyright (C) 2017-2020 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Quagga\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class BgpController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'bgp';
    protected static $internalModelClass = '\OPNsense\Quagga\BGP';

    public function searchNeighborAction()
    {
        return $this->searchBase(
            'neighbors.neighbor',
            array("enabled",
                  "description",
                  "address",
                  "remoteas",
                  "password",
                  "localip",
                  "updatesource",
                  "nexthopself",
                  "multihop",
                  "keepalive",
                  "holddown",
                  "connecttimer",
                  "defaultoriginate",
                  "asoverride",
                  "linkedPrefixlistIn",
                  "linkedPrefixlistOut",
                  "linkedRoutemapIn",
                  "linkedRoutemapOut")
        );
    }

    public function getNeighborAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('neighbor', 'neighbors.neighbor', $uuid);
    }

    public function addNeighborAction()
    {
        return $this->addBase('neighbor', 'neighbors.neighbor');
    }

    public function delNeighborAction($uuid)
    {
        return $this->delBase('neighbors.neighbor', $uuid);
    }

    public function setNeighborAction($uuid)
    {
        return $this->setBase('neighbor', 'neighbors.neighbor', $uuid);
    }

    public function searchAspathAction()
    {
        return $this->searchBase(
            'aspaths.aspath',
            array("enabled", "description", "number", "action", "as" )
        );
    }

    public function getAspathAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('aspath', 'aspaths.aspath', $uuid);
    }

    public function addAspathAction()
    {
        return $this->addBase('aspath', 'aspaths.aspath');
    }

    public function delAspathAction($uuid)
    {
        return $this->delBase('aspaths.aspath', $uuid);
    }

    public function setAspathAction($uuid)
    {
        return $this->setBase('aspath', 'aspaths.aspath', $uuid);
    }

    public function searchPrefixlistAction()
    {
        return $this->searchBase(
            'prefixlists.prefixlist',
            array("enabled", "description", "name", "seqnumber", "action", "network" )
        );
    }
    public function getPrefixlistAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('prefixlist', 'prefixlists.prefixlist', $uuid);
    }

    public function addPrefixlistAction()
    {
        return $this->addBase('prefixlist', 'prefixlists.prefixlist');
    }

    public function delPrefixlistAction($uuid)
    {
        return $this->delBase('prefixlists.prefixlist', $uuid);
    }

    public function setPrefixlistAction($uuid)
    {
        return $this->setBase('prefixlist', 'prefixlists.prefixlist', $uuid);
    }

    public function searchCommunitylistAction()
    {
        return $this->searchBase(
            'communitylists.communitylist',
            array("enabled", "description", "number", "seqnumber", "action", "community" )
        );
    }
    public function getCommunitylistAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('communitylist', 'communitylists.communitylist', $uuid);
    }

    public function addCommunitylistAction()
    {
        return $this->addBase('communitylist', 'communitylists.communitylist');
    }

    public function delCommunitylistAction($uuid)
    {
        return $this->delBase('communitylists.communitylist', $uuid);
    }

    public function setCommunitylistAction($uuid)
    {
        return $this->setBase('communitylist', 'communitylists.communitylist', $uuid);
    }

    public function searchRoutemapAction()
    {
        return $this->searchBase(
            'routemaps.routemap',
            array("enabled", "description", "name", "action", "id", "match", "match2", "set")
        );
    }

    public function getRoutemapAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('routemap', 'routemaps.routemap', $uuid);
    }

    public function addRoutemapAction()
    {
        return $this->addBase('routemap', 'routemaps.routemap');
    }

    public function delRoutemapAction($uuid)
    {
        return $this->delBase('routemaps.routemap', $uuid);
    }

    public function setRoutemapAction($uuid)
    {
        return $this->setBase('routemap', 'routemaps.routemap', $uuid);
    }

    public function toggleCommunitylistAction($uuid)
    {
        return $this->toggleBase('communitylists.communitylist', $uuid);
    }

    public function toggleNeighborAction($uuid)
    {
        return $this->toggleBase('neighbors.neighbor', $uuid);
    }

    public function toggleAspathAction($uuid)
    {
        return $this->toggleBase('aspaths.aspath', $uuid);
    }

    public function togglePrefixlistAction($uuid)
    {
        return $this->toggleBase('prefixlists.prefixlist', $uuid);
    }

    public function toggleRoutemapAction($uuid)
    {
        return $this->toggleBase('routemaps.routemap', $uuid);
    }
}
