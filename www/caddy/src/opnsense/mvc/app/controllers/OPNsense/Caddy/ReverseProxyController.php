<?php

/**
 *    Copyright (C) 2023-2025 Cedrik Pischem
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

namespace OPNsense\Caddy;

use OPNsense\Base\IndexController;

class ReverseProxyController extends IndexController
{
    public function indexAction()
    {
        $this->view->entrypoint = 'reverse_proxy';
        $this->view->pick('OPNsense/Caddy/reverse_proxy');

        $this->view->formDialogReverseProxy = $this->getForm("dialogReverseProxy");
        $this->view->formGridReverseProxy = $this->getFormGrid('dialogReverseProxy', null, 'ConfChangeMessage');

        $this->view->formDialogSubdomain = $this->getForm("dialogSubdomain");
        $this->view->formGridSubdomain = $this->getFormGrid('dialogSubdomain', null, 'ConfChangeMessage');

        $this->view->formDialogHandle = $this->getForm("dialogHandle");
        $this->view->formGridHandle = $this->getFormGrid('dialogHandle', null, 'ConfChangeMessage');

        $this->view->formDialogAccessList = $this->getForm("dialogAccessList");
        $this->view->formGridAccessList = $this->getFormGrid('dialogAccessList', null, 'ConfChangeMessage');

        $this->view->formDialogBasicAuth = $this->getForm("dialogBasicAuth");
        $this->view->formGridBasicAuth = $this->getFormGrid('dialogBasicAuth', null, 'ConfChangeMessage');

        $this->view->formDialogHeader = $this->getForm("dialogHeader");
        $this->view->formGridHeader = $this->getFormGrid('dialogHeader', null, 'ConfChangeMessage');
    }
}
