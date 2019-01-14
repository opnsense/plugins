<?php

/*
 * Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\ClamAV\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\ClamAV
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\ClamAV\General';
    protected static $internalServiceTemplate = 'OPNsense/ClamAV';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'clamav';

    /**
     * load the initial signatures
     * @return array
     */
    public function freshclamAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $command = 'clamav freshclam';
            if ($this->request->hasPost('action')) {
                $command .= ' go';
            }
            $response = trim($backend->configdRun($command));
            return array('status' => $response);
        } else {
            return array('status' => 'error');
        }
    }

    /**
     * get ClamAV and signature versions
     */
    public function versionAction()
    {
        $infos = array(
            "clamav" => array("Version"),
            "main" => array("main.cvd", "main.cld"),
            "daily" => array("daily.cvd", "daily.cld"),
            "bytecode" => array("bytecode.cvd", "bytecode.cld"),
            "signatures" => array("Total number of signatures")
        );
        $backend = new Backend();
        $result = array();
        $response = json_decode($backend->configdRun("clamav version"));
        if ($response != null) {
            foreach ($response as $key => $value) {
                foreach ($infos as $info_key => $info) {
                    if (in_array($key, $info)) {
                        $result[$info_key] = $value;
                    }
                }
            }
            return array("version" => $result);
        } else {
            return array();
        }
    }
}
