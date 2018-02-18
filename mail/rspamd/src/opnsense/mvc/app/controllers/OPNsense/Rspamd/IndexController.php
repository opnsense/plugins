<?php
/*

    Copyright (C) 2017 Fabian Franz
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.


*/


namespace OPNsense\Rspamd;

use \OPNsense\Core\Backend;
use \OPNsense\Rspamd\RSpamd;

/**
* Class IndexController
* @package OPNsense/Rspamd
*/
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $backend = new Backend();
        $this->view->clamav_installed = (trim($backend->configdRun('firmware plugin clamav')) == '1');
        $this->view->redis_installed = (trim($backend->configdRun('firmware plugin redis')) == '1');
        $this->view->redis_plugin_enabled = ((string)((new RSpamd())->general->enable_redis_plugin)) == '1';
        $this->view->settings = $this->getForm("settings");
        $this->view->pick('OPNsense/Rspamd/index');
    }
}
