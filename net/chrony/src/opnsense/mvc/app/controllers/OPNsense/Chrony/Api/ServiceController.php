<?php

/*
 * Copyright (C) 2020-2021 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Chrony\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Chrony\General;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Chrony\General';
    protected static $internalServiceTemplate = 'OPNsense/Chrony';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'chrony';

    /**
     * show chrony sources
     * @return array
     */
    public function chronysourcesAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("chrony chronysources");
        return array("response" => $response);
    }

    /**
     * show chrony stats
     * @return array
     */
    public function chronysourcestatsAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("chrony chronysourcestats");
        return array("response" => $response);
    }

    /**
     * show chrony tracking
     * @return array
     */
    public function chronytrackingAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("chrony chronytracking");
        return array("response" => $response);
    }

    /**
     * show chrony authdata
     * @return array
     */
    public function chronyauthdataAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("chrony chronyauthdata");
        return array("response" => $response);
    }

    /**
     * show chrony ntpdata
     * @return array
     */
    public function chronyntpdataAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("chrony chronyntpdata");
        return array("response" => $response);
    }
}
