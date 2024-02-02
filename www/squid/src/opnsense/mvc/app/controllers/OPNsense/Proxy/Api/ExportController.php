<?php

/**
 *    Copyright (C) 2024 0xThiebaut
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

namespace OPNsense\Proxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Proxy\Proxy;

/**
 * Class ExportController
 * @package OPNsense\Proxy
 */
class ExportController extends ApiControllerBase
{
    /**
     * download TLS keys
     * @return array|mixed
     */
    public function downloadAction()
    {
        $this->sessionClose();
        $result = (new Backend())->configdRun("proxy export_keys");
        $filename = '/tmp/squid-tlskeys.zip';
        if (!empty($result) && !empty($filename) && filesize($filename) > 0) {
            $this->response->setContentType('application/zip');
            $this->response->setRawHeader("Content-Disposition: attachment; filename=" . basename($filename));
            $this->response->setRawHeader("Content-length: " . filesize($filename));
            $this->response->setRawHeader("Pragma: no-cache");
            $this->response->setRawHeader("Expires: 0");
            ob_clean();
            flush();
            readfile($filename);
        }
    }
}
