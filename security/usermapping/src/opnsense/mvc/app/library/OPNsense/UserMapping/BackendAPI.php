<?php
/**
 *    Copyright (C) 2019 Fabian Franz
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

namespace OPNsense\UserMapping;


class BackendAPI
{

    static private $SOCKET_PATH = 'unix:///var/run/usermapping';

    public function log_in($ip, $user, $groups = null, $valid_time = null) {
        $login_data = array(
            'method' => 'login',
            'username' => $user,
            'ip' => $ip);
        if (!empty($groups) && is_array($groups)) {
            $login_data['groups'] = $groups;
        }
        if (!empty($valid_time) && is_integer($valid_time)) {
            $login_data['valid_until'] = $valid_time;
        }
        return $this->send_command(json_encode($login_data));
    }
    public function log_out($ip) {
        $login_data = array(
            'method' => 'logout',
            'ip' => $ip);
        return $this->send_command(json_encode($login_data));
    }
    public function who_is($ip) {
        $login_data = array(
            'method' => 'whois',
            'ip' => $ip);
        return $this->send_command(json_encode($login_data));
    }
    public function exit() {
        $login_data = array(
            'method' => 'exit');
        return $this->send_command(json_encode($login_data));
    }
    public function list() {
        $login_data = array(
            'method' => 'list');
        return $this->send_command(json_encode($login_data));
    }

    private function send_command($command)
    {
        try {
            $socket = @stream_socket_client(BackendAPI::$SOCKET_PATH, $error_code, $error_msg);
        } catch (\Exception $e) {
            $socket = null;
        }
        if ($socket == null) {
            return array('error' => 'authentication service not available');
        }
        fwrite($socket, $command . PHP_EOL);
        $data = fgets($socket);
        fclose($socket);
        return json_decode($data, true);
    }
}
