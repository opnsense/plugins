<?php

/*

    Copyright (C) 2018-2020 Fabian Franz
    Copyright (C) 2020 Manuel Faux
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

    /**
     * "/" -> list of access logs
     * "/uuid" -> conent of access log
     * @param null|string $uuid log uuid of the HTTP server from which the error log should be returned
     * @return array if feasible, otherwise null and the data is sent directly back
     * @throws \OPNsense\Base\ModelException ?
     */
    public function accessesAction($uuid = null, $page = 0, $perPage = 0, $query = "")
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /accesses delivers a list of servers with access logs
            return $this->list_vhosts();
        } else {
            // emulate REST call for a specific log /accesses/uuid
            $this->call_configd('access', $uuid, $page, $perPage, $query);
        }
    }

    /**
     * action to query the TLS handshake information - useful for building fingerprint databases
     * @throws \Exception
     */
    public function tlsHandshakesAction()
    {
        $this->sendConfigdToClient('nginx tls_handshakes');
    }

    /**
     * "/" -> list of error logs
     * "/uuid" -> conent of error log
     * @param null|string $uuid uuid of the HTTP server from which the error log should be returned
     * @return array if feasible, otherwise null and the data is sent directly back
     * @throws \OPNsense\Base\ModelException ?
     */
    public function errorsAction($uuid = null, $page = 0, $perPage = 0, $query = "")
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /errors delivers a list of servers with error logs
            return $this->list_vhosts();
        } else {
            // emulate REST call for a specific log /errors/uuid
            $this->call_configd('error', $uuid, $page, $perPage, $query);
        }
    }

    /**
     * "/" -> list of access logs
     * "/uuid" -> conent of access log
     * @param null|string $uuid log uuid of the stream server from which the error log should be returned
     * @return array if feasible, otherwise null and the data is sent directly back
     * @throws \OPNsense\Base\ModelException ?
     */
    public function stream_accessesAction($uuid = null, $page = 0, $perPage = 0, $query = "")
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /stream_accesses delivers a list of servers with access logs
            return $this->list_streams();
        } else {
            // emulate REST call for a specific log /stream_accesses/uuid
            $this->call_configd_stream('streamaccess', $uuid, $page, $perPage, $query);
        }
    }

    /**
     * "/" -> list of access logs
     * "/uuid" -> conent of error log
     * @param null $uuid uuid of the stream server from which the error log should be returned
     * @return array if feasible, otherwise null and the data is sent directly back
     * @throws \OPNsense\Base\ModelException ?
     */
    public function stream_errorsAction($uuid = null, $page = 0, $perPage = 0, $query = "")
    {
        $this->nginx = new Nginx();
        if (!isset($uuid)) {
            // emulate REST API -> /stream_errors delivers a list of servers with error logs
            return $this->list_streams();
        } else {
            // emulate REST call for a specific log /stream_errors/uuid
            $this->call_configd_stream('streamerror', $uuid, $page, $perPage, $query);
        }
    }


    /**
     * @param $type string access or error for the used log type
     * @param $uuid string uuid of the server
     * @return |null
     * @throws \Exception ?
     */
    private function call_configd($type, $uuid, $page, $perPage, $query)
    {
        if (!($this->vhost_exists($uuid) || $uuid == 'global')) {
            $this->response->setStatusCode(404, "Not Found");
        }

        $page = intval($page);
        $perPage = intval($perPage);
        $query = str_replace('"', '\"', urldecode($query));

        return $this->sendConfigdToClient("nginx log $type $uuid $page $perPage $query");
    }
    /**
     * @param $type string access or error for the used log type
     * @param $uuid string uuid of the server
     * @return |null
     * @throws \Exception ?
     */
    private function call_configd_stream($type, $uuid, $page, $perPage)
    {
        if (!$this->stream_exists($uuid)) {
            $this->response->setStatusCode(404, "Not Found");
        }

        return $this->sendConfigdToClient("nginx log $type $uuid $page $perPage");
    }

    /**
     * @return array list of HTTP servers
     */
    private function list_vhosts()
    {
        $data = [];
        foreach ($this->nginx->http_server->iterateItems() as $item) {
            $data[] = array('id' => $item->getAttributes()['uuid'], 'server_name' => (string)$item->servername);
        }
        return $data;
    }

    /**
     * @return array list of stream servers
     */
    private function list_streams()
    {
        $data = [];
        foreach ($this->nginx->stream_server->iterateItems() as $item) {
            $data[] = array('id' => $item->getAttributes()['uuid'], 'port' => (string)$item->listen_address);
        }
        return $data;
    }

    /**
     * @param $uuid string uuid of the HTTP server to check
     * @return bool true if the HTTP server configuration exists
     */
    private function vhost_exists($uuid)
    {
        $data = $this->nginx->getNodeByReference('http_server.' . $uuid);
        return isset($data);
    }

    /**
     * @param $uuid string uuid of the stream server to check
     * @return bool true if the stream configuration exists
     */
    private function stream_exists($uuid)
    {
        $data = $this->nginx->getNodeByReference('stream_server.' . $uuid);
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
