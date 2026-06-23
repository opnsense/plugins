<?php

/**
 *    Copyright (C) 2016-2022 Frank Wall
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
        $this->view->formDialogAcl = $this->getForm("dialogAcl");
        $this->view->formDialogAction = $this->getForm("dialogAction");
        $this->view->formDialogBackend = $this->getForm("dialogBackend");
        $this->view->formDialogCpu = $this->getForm("dialogCpu");
        $this->view->formDialogErrorfile = $this->getForm("dialogErrorfile");
        $this->view->formDialogFcgi = $this->getForm("dialogFcgi");
        $this->view->formDialogFrontend = $this->getForm("dialogFrontend");
        $this->view->formDialogGroup = $this->getForm("dialogGroup");
        $this->view->formDialogHealthcheck = $this->getForm("dialogHealthcheck");
        $this->view->formDialogLua = $this->getForm("dialogLua");
        $this->view->formDialogMailer = $this->getForm("dialogMailer");
        $this->view->formDialogMapfile = $this->getForm("dialogMapfile");
        $this->view->formDialogResolver = $this->getForm("dialogResolver");
        $this->view->formDialogServer = $this->getForm("dialogServer");
        $this->view->formDialogUser = $this->getForm("dialogUser");
        $this->view->generalCacheForm = $this->getForm("generalCache");
        $this->view->generalDefaultsForm = $this->getForm("generalDefaults");
        $this->view->generalLoggingForm = $this->getForm("generalLogging");
        $this->view->generalPeersForm = $this->getForm("generalPeers");
        $this->view->generalSettingsForm = $this->getForm("generalSettings");
        $this->view->generalStatsForm = $this->getForm("generalStats");
        $this->view->generalTuningForm = $this->getForm("generalTuning");
        // set additional view parameters
        $mdlHAProxy = new \OPNsense\HAProxy\HAProxy();
        $this->view->showIntro = (string)$mdlHAProxy->general->showIntro;
        // pick the template to serve
        $this->view->pick('OPNsense/HAProxy/index');
    }
}
