<?php

/*
 * Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\Postfix\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Postfix\General;

/**
 * Class ServiceController
 * @package OPNsense\Postfix
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Postfix\General';
    protected static $internalServiceTemplate = 'OPNsense/Postfix';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'postfix';

    /**
     * check rspamd
     * @return array
     */
    public function checkrspamdAction()
    {
        $backend = new Backend();
        $mdlGeneral = new General();
        $response = $backend->configdRun("firmware plugin rspamd");
        return $response;
    }

    /**
     * reconfigure postfix, generate config and reload
     *
     * XXX overwrites the base one for make-transport
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $mdlGeneral = new General();
            $backend = new Backend();

            $runStatus = $this->statusAction();

            // stop postfix if it is running or not
            $this->stopAction();

            // generate template
            $backend->configdRun('template reload OPNsense/Postfix');
            $backend->configdRun('postfix make-transport');

            // (res)start daemon
            if ($mdlGeneral->enabled->__toString() == 1) {
                $this->startAction();
            }

            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }
}
