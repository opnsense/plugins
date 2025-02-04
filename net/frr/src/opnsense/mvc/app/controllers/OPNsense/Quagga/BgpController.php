<?php

/*
 * Copyright (C) 2017 Fabian Franz
 * Copyright (C) 2017-2024 Michael Muenz <m.muenz@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
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

namespace OPNsense\Quagga;

class BgpController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->bgpForm = $this->getForm("bgp");

        $this->view->formDialogEditBGPNeighbor = $this->getForm("dialogEditBGPNeighbor");
        $this->view->formGridEditBGPNeighbor = $this->getFormGrid("dialogEditBGPNeighbor", null, "BGPChangeMessage");

        $this->view->formDialogEditBGPASPaths = $this->getForm("dialogEditBGPASPath");
        $this->view->formGridEditBGPASPaths = $this->getFormGrid("dialogEditBGPASPath", null, "BGPChangeMessage");

        $this->view->formDialogEditBGPPrefixLists = $this->getForm("dialogEditBGPPrefixLists");
        $this->view->formGridEditBGPPrefixLists = $this->getFormGrid("dialogEditBGPPrefixLists", null, "BGPChangeMessage");

        $this->view->formDialogEditBGPCommunityLists = $this->getForm("dialogEditBGPCommunityLists");
        $this->view->formGridEditBGPCommunityLists = $this->getFormGrid("dialogEditBGPCommunityLists", null, "BGPChangeMessage");

        $this->view->formDialogEditBGPRouteMaps = $this->getForm("dialogEditBGPRouteMaps");
        $this->view->formGridEditBGPRouteMaps = $this->getFormGrid("dialogEditBGPRouteMaps", null, "BGPChangeMessage");

        $this->view->formDialogEditBGPPeergroups = $this->getForm("dialogEditBGPPeergroups");
        $this->view->formGridEditBGPPeergroups = $this->getFormGrid("dialogEditBGPPeergroups", null, "BGPChangeMessage");

        $this->view->pick('OPNsense/Quagga/bgp');
    }
}
