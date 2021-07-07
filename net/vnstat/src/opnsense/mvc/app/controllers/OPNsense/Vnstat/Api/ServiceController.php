<?php

/**
 *    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Vnstat\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Vnstat\General;

/**
 * Class ServiceController
 * @package OPNsense\Vnstat
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Vnstat\General';
    protected static $internalServiceTemplate = 'OPNsense/Vnstat';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'vnstat';

    /**
     * list hourly statistics
     * @return array
     */
    public function hourlyAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("vnstat hourly");
        return array("response" => $response);
    }

    /**
     * list daily statistics
     * @return array
     */
    public function dailyAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("vnstat daily");
        return array("response" => $response);
    }

    /**
     * list monthly statistics
     * @return array
     */
    public function monthlyAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("vnstat monthly");
        return array("response" => $response);
    }

    /**
     * list yearly statistics
     * @return array
     */
    public function yearlyAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("vnstat yearly");
        return array("response" => $response);
    }

    /**
     * remove database folder
     * @return array
     */
    public function resetdbAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("vnstat resetdb");
        return array("response" => $response);
    }
}
