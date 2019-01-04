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

namespace OPNsense\ProxyUserACL;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // set page title, used by the standard template in layouts/default.volt.
        // pick the template to serve to our users.
        $this->view->mainForm = $this->getForm("main");
        $this->view->tabs = [
            [
                'name' => 'users',
                'title' => gettext('Users and groups'),
                'formDialog' => $this->getForm("dialogUsers"),
                'fields' => [gettext('Server'), gettext('Group')]
            ],
            [
                'name' => 'macs',
                'title' => gettext('MAC-addresses'),
                'formDialog' => $this->getForm("dialogMacs"),
                'fields' => []
            ],
            [
                'name' => 'srcs',
                'title' => gettext('Sources nets'),
                'formDialog' => $this->getForm("dialogSrcs"),
                'fields' => []
            ],
            [
                'name' => 'dsts',
                'title' => gettext('Destination nets'),
                'formDialog' => $this->getForm("dialogDsts"),
                'fields' => []
            ],
            [
                'name' => 'domains',
                'title' => gettext('Destination domains'),
                'formDialog' => $this->getForm("dialogDomains"),
                'fields' => []
            ],
            [
                'name' => 'agents',
                'title' => gettext('Browser user agents'),
                'formDialog' => $this->getForm("dialogAgents"),
                'fields' => []
            ],
            [
                'name' => 'mimes',
                'title' => gettext('Mime types'),
                'formDialog' => $this->getForm("dialogMimes"),
                'fields' => []
            ],
            [
                'name' => 'times',
                'title' => gettext('Schedules'),
                'formDialog' => $this->getForm("dialogTimes"),
                'fields' => []
            ],
        ];
        $this->view->formDialogHttpaccesses = $this->getForm("dialogHttpaccesses");
        $this->view->pick('OPNsense/ProxyUserACL/index');
    }
}
