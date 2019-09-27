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

namespace OPNsense\Wireguard\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Wireguard\General;
use OPNsense\Wireguard\Client;

/**
 * Class ServiceController
 * @package OPNsense\Wireguard
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Wireguard\General';
    protected static $internalServiceTemplate = 'OPNsense/Wireguard';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'wireguard';

    /**
     * show wireguard config
     * @return array
     */
    public function showconfAction()
    {
        $backend = new Backend();
        $response_org = $backend->configdRun("wireguard showconf");      
        $response = '';

        $pubnames = $this->getPubkeyNames();
        $rp_lines = preg_split('/\r\n|\r|\n/', $response_org);
        foreach($rp_lines as $line)
        {
            if(substr($line, 0, 6) == 'peer: ')
            {
                $key = trim(substr($line, 6));
                if(isset($pubnames[$key])) $line.= ' * '. $pubnames[$key];
            }
            $response.= $line.PHP_EOL;
        }
        return array("response" => $response);
    }

    /**
     * show wireguard handshakes
     * @return array
     */
    public function showhandshakeAction()
    {
        $curtime = time();
        $backend = new Backend();
        $response_org = $backend->configdRun("wireguard showhandshake");
        $response = '';

        $pubnames = $this->getPubkeyNames();
        $rp_lines = preg_split('/\r\n|\r|\n/', $response_org);
        foreach($rp_lines as $line)
        {
            $cols = preg_split('/[\s\t]{1,}/', $line);
            if(count($cols) > 2)
            {
                $name = isset($pubnames[$cols[1]]) ? $pubnames[$cols[1]] : '<UNKNOWN>';
                $date = !empty($cols[2]) ? ($curtime - intval($cols[2])).' sec. ago' : 'NOT CONNECTED';
                $extratab = empty($cols[2]) ? "\t\t" : "\t";
                $response.= $line. $extratab.$date."\t".$name.PHP_EOL;
            } else {
                $response.= $line.PHP_EOL;
            }
        }

        return array("response" => $response);
    }

    /**
     * build Dictionary pubkey => name
     * @return array
     */
    private function getPubkeyNames()
    {
        $mdlclients = new Client();
        $search = $mdlclients->getNodes();

        $ret = array();
        if(is_array($search['clients']['client']))
        {
            foreach($search['clients']['client'] as $client)
            {
                $ret[$client['pubkey']] = $client['name'];
            }
        }
        return $ret;
    }

}
