<?php

/*
 * Copyright 2021 Miha Kralj
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
 */
namespace OPNsense\Speedtest\Api;

use OPNsense\Base\ApiControllerBase;

class DownloadController extends ApiControllerBase
{
    private const DATA_CSV = '/usr/local/opnsense/scripts/OPNsense/speedtest/speedtest.csv';

    public function csvAction()
    {
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('text/csv', 'UTF-8');
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"speedtest.csv\"");
        $data = file_get_contents(self::DATA_CSV);
        $this->response->setContent($data);
    }

    public function afterExecuteRoute($dispatcher)
    {
        $this->response->send();
    }
}
