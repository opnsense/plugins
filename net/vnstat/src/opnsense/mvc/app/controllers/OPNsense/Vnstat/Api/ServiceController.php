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
use OPNsense\Core\Config;
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
        return $this->getStatsForDisplayInterfaces('hourly');
    }

    /**
     * list daily statistics
     * @return array
     */
    public function dailyAction()
    {
        return $this->getStatsForDisplayInterfaces('daily');
    }

    /**
     * list monthly statistics
     * @return array
     */
    public function monthlyAction()
    {
        return $this->getStatsForDisplayInterfaces('monthly');
    }

    /**
     * list yearly statistics
     * @return array
     */
    public function yearlyAction()
    {
        return $this->getStatsForDisplayInterfaces('yearly');
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

    private function getStatsForDisplayInterfaces(string $type) {
        $config = Config::getInstance()->toArray();
        $backend = new Backend();

        if (!isset($config['OPNsense']['vnstat']['general']['interface_display'])) {
            // no interface configured, use script default (i.e. don't specify interface)
            $response = $backend->configdRun("vnstat $type");
            return array("response" => $response);
        }

        // loop over configured interfaces, combining the output
        $result = '';
        foreach (explode(',', $config['OPNsense']['vnstat']['general']['interface_display']) as $interface) {
            // map the OPNsense interface name to the kernel interface name that vnstat uses
            if (isset($config['interfaces'][$interface]['if'])) {
                $result .= $backend->configdpRun("vnstat", [ $type, $config['interfaces'][$interface]['if'] ]);
            }
        }
        return array("response" => $result);
    }
}
