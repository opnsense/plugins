<?php

/*
 * Copyright (C) 2017-2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2016 IT-assistans Sverige AB
 * Copyright (C) 2015-2016 Deciso B.V.
 * Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Nginx\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Nginx\Nginx';
    protected static $internalServiceTemplate = 'OPNsense/Nginx';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'nginx';

    /**
    *  override parent method - stopping nginx is not allowed because otherwise you would loose
    *  access to the web interface
    */
    public function stopAction()
    {
        return array('status' => 'failed');
    }

    /**
     * retrieve status of service
     * @return array response message
     * @throws \Exception when configd action fails
     */
    public function statusAction()
    {
        $backend = new Backend();
        $model = $this->getModel();
        $response = $backend->configdRun('nginx status');

        if (strpos($response, 'not running') > 0) {
            $status = 'stopped';
        } elseif (strpos($response, 'is running') > 0) {
            $status = 'running';
        } else {
            $status = 'unknown';
        }

        return array('status' => $status);
    }

    /**
     * retrieve extended status of service
     * @return array response message
     * @throws \Exception when configd action fails
     */
    public function vtsAction()
    {
        $backend = new Backend();
        $vts = json_decode($backend->configdRun('nginx vts'), true);
        if ($vts != null) {
            return $vts;
        }

        $this->response->setStatusCode(404, "Not Found");
        return array();
    }

    protected function reconfigureForceRestart()
    {
        return 0;
    }
}
