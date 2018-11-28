<?php

/**
 *    Copyright (C) 2016 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
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
 */

namespace OPNsense\HAProxy;

use OPNsense\HAProxy\HAProxy;

/**
 * Class IndexController
 * @package OPNsense\HAProxy
 */
class IndexController extends \OPNsense\Base\IndexController
{
    /**
     * haproxy index page
     * @throws \Exception
     */
    public function indexAction()
    {
        // include form definitions
        $this->view->mainForm = $this->getForm("main");
        $this->view->formDialogFrontend = $this->getForm("dialogFrontend");
        $this->view->formDialogBackend = $this->getForm("dialogBackend");
        $this->view->formDialogServer = $this->getForm("dialogServer");
        $this->view->formDialogHealthcheck = $this->getForm("dialogHealthcheck");
        $this->view->formDialogAction = $this->getForm("dialogAction");
        $this->view->formDialogAcl = $this->getForm("dialogAcl");
        $this->view->formDialogUser = $this->getForm("dialogUser");
        $this->view->formDialogGroup = $this->getForm("dialogGroup");
        $this->view->formDialogLua = $this->getForm("dialogLua");
        $this->view->formDialogErrorfile = $this->getForm("dialogErrorfile");
        $this->view->formDialogMapfile = $this->getForm("dialogMapfile");
        $this->view->formDialogCpu = $this->getForm("dialogCpu");
        // set additional view parameters
        $mdlHAProxy = new \OPNsense\HAProxy\HAProxy();
        $this->view->showIntro = (string)$mdlHAProxy->general->showIntro;
        // pick the template to serve
        $this->view->pick('OPNsense/HAProxy/index');
    }
}
