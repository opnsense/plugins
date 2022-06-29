<?php

/*
 * Copyright (C) 2017-2019 Frank Wall
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\AcmeClient\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\AcmeClient\AcmeClient;

/**
 * Class ActionsController
 * @package OPNsense\AcmeClient
 */
class ActionsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'acmeclient';
    protected static $internalModelClass = '\OPNsense\AcmeClient\AcmeClient';

    public function getAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('action', 'actions.action', $uuid);
    }

    public function addAction()
    {
        return $this->addBase('action', 'actions.action');
    }

    public function updateAction($uuid)
    {
        return $this->setBase('action', 'actions.action', $uuid);
    }

    public function delAction($uuid)
    {
        return $this->delBase('actions.action', $uuid);
    }

    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('actions.action', $uuid);
    }

    public function searchAction()
    {
        return $this->searchBase('actions.action', array('enabled', 'name', 'type', 'description'), 'name');
    }

    public function sftpGetIdentityAction()
    {
        $result = ["status" => "unavailable"];

        if ($response = $this->callBackend(["show-sftp-identity"], ["sftp_identity_type", "sftp_host"])) {
            $result["status"] = "ok";
            $result["identity"] = $response;
        }

        return $result;
    }

    public function sftpTestConnectionAction()
    {
        if (
            $response = $this->callBackend(
                ["test-sftp-connection"],
                ["sftp_host", "sftp_host_key", "sftp_port", "sftp_user", "sftp_identity_type", "sftp_remote_path", "sftp_chmod", "sftp_chgrp"]
            )
        ) {
            return $response;
        }

        return ["status" => "unavailable"];
    }

    public function sshGetIdentityAction()
    {
        $result = ["status" => "unavailable"];

        if ($response = $this->callBackend(["show-remote-ssh-identity"], ["remote_ssh_identity_type", "remote_ssh_host"])) {
            $result["status"] = "ok";
            $result["identity"] = $response;
        }

        return $result;
    }

    public function sshTestConnectionAction()
    {
        if (
            $response = $this->callBackend(
                ["test-remote-ssh-connection"],
                ["remote_ssh_host", "remote_ssh_host_key", "remote_ssh_port", "remote_ssh_user", "remote_ssh_identity_type"]
            )
        ) {
            return $response;
        }

        return ["status" => "unavailable"];
    }

    private function callBackend(array $command, array $arguments = [])
    {
        if ($this->request->isPost()) {
            $backend = new Backend();

            foreach ($arguments as $name) {
                $command[] = $this->request->getPost($name);
            }

            $command = array_map(function ($value) {
                return escapeshellarg(empty($value = trim($value)) ? "__default_value" : $value);
            }, $command);

            if ($result = trim($backend->configdRun("acmeclient " . join(" ", $command)))) {
                if (preg_match('/^\[.+\]$/ms', $result) || preg_match('/^\{.+\}$/ms', $result)) {
                    try {
                        $result = json_decode($result, true, 64, JSON_THROW_ON_ERROR);
                    } catch (\Exception $ignored) {
                        /* pass as is when json parsing fails */
                    }
                }
                return $result;
            }
        }

        return false;
    }
}
