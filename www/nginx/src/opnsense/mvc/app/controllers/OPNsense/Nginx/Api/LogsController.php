<?php
/*

    Copyright (C) 2018 Fabian Franz
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
namespace OPNsense\Nginx\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Nginx\Nginx;

class LogsController extends ApiControllerBase
{
    private $nginx;
    public function accessesAction($uuid = null)
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /accesses delivers a list of servers with access logs
            return $this->list_vhosts();
        } else {
            // emulate REST call for a specific log /accesses/uuid
            $this->call_configd('access', $uuid);
        }
    }

    public function errorsAction($uuid = null)
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /errors delivers a list of servers with error logs
            return $this->list_vhosts();
        } else {
            // emulate REST call for a specific log /errors/uuid
            $this->call_configd('error', $uuid);
        }
    }

    public function stream_accessesAction($uuid = null)
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /stream_accesses delivers a list of servers with access logs
            return $this->list_streams();
        } else {
            // emulate REST call for a specific log /stream_accesses/uuid
            $this->call_configd_stream('streamaccess', $uuid);
        }
    }

    public function stream_errorsAction($uuid = null)
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /stream_errors delivers a list of servers with error logs
            return $this->list_streams();
        } else {
            // emulate REST call for a specific log /stream_errors/uuid
            $this->call_configd_stream('streamerror', $uuid);
        }
    }


    private function call_configd($type, $uuid)
    {
        if (!$this->vhost_exists($uuid)) {
            $this->response->setStatusCode(404, "Not Found");
        }

        return $this->sendConfigdToClient('nginx log ' . $type . ' ' . $uuid);
    }
    private function call_configd_stream($type, $uuid)
    {
        if (!$this->stream_exists($uuid)) {
            $this->response->setStatusCode(404, "Not Found");
        }

        return $this->sendConfigdToClient('nginx log ' . $type . ' ' . $uuid);
    }

    private function list_vhosts()
    {
        $data = [];
        foreach ($this->nginx->http_server->iterateItems() as $item) {
            $data[] = array('id' => $item->getAttributes()['uuid'], 'server_name' => (string)$item->servername);
        }
        return $data;
    }
    private function list_streams()
    {
        $data = [];
        foreach ($this->nginx->stream_server->iterateItems() as $item) {
            $data[] = array('id' => $item->getAttributes()['uuid'], 'port' => (string)$item->listen_port);
        }
        return $data;
    }

    private function vhost_exists($uuid)
    {
        $data = $this->nginx->getNodeByReference('http_server.'. $uuid);
        return isset($data);
    }
    private function stream_exists($uuid)
    {
        $data = $this->nginx->getNodeByReference('stream_server.'. $uuid);
        return isset($data);
    }

    /**
     * @param $command String JSON generating configd command
     * @return null
     * @throws \Exception ?
     */
    private function sendConfigdToClient($command)
    {
        $backend = new Backend();
        // must be passed directly -> OOM Problem
        $this->response->setContent($backend->configdRun($command));
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('application/json', 'UTF-8');
        return $this->response->send();
    }
}
