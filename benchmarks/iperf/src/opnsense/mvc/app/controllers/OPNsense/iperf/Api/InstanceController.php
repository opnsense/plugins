<?php

/*
 *    Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\iperf\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Iperf\FakeInstance;

class InstanceController extends ApiMutableModelControllerBase
{
    static protected $internalModelClass = '\OPNsense\iperf\FakeInstance';
    static protected $internalModelName = 'instance';
    static private $SOCKET_PATH = "unix:///var/run/iperf-manager.sock";

  // override base to set model - not used here
    public function setAction()
    {
        $backend = new Backend();

      // if no socket file exist, we know that the service is not running
        if (!file_exists("/var/run/iperf-manager.sock")) {
            $backend->configdRun('iperf start');
        }
        if (!isset($_POST['instance']['interface'])) {
            return array('status' => 'error',
                    'error' => 'interface parameter is missing');
        }
        $interface_name = $_POST['instance']['interface'];
        if ($interface = $this->get_real_interface_name($interface_name)) {
            // start iperf
            return $this->send_command("start $interface", $backend);
        } else {
            return array('status' => 'error',
                      'error' => 'interface is unknown');
        }
    }

    public function queryAction()
    {
        $backend = new Backend();
        return $this->send_command('query', $backend);
    }

    private function send_command($command, $backend)
    {
        try {
            $socket = @stream_socket_client(InstanceController::$SOCKET_PATH, $error_code, $error_msg);
        } catch (\Exception $e) {
            $socket = null;
        }
        if (!$socket) {
            // in case of an error: try to restart the service and if that fails too
            // don't retry anymore
            $backend->configdRun('iperf restart');
            $socket = @stream_socket_client(InstanceController::$SOCKET_PATH, $error_code, $error_msg);
            if (!$socket) {
                return array('state' => 'error', 'code' => $error_code, 'msg' => $error_msg);
            }
        }
        fwrite($socket, "$command\n");
        $data = fgets($socket);
        fwrite($socket, "bye\n");
        fgets($socket);
        fclose($socket);
        return json_decode($data, true);
    }
    private function get_real_interface_name($name)
    {
        $config = Config::getInstance()->toArray();
        if (isset($config['interfaces'][$name])) {
            return $config['interfaces'][$name]['if'];
        }
        return null;
    }
}
