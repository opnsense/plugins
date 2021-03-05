<?php

/**
 *    Copyright (C) 2021 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\HAProxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\HAProxy\HAProxy;

/**
 * Class StatisticsController
 * @package OPNsense\HAProxy
 */
class ExportController extends ApiControllerBase
{
    /**
     * get config
     * @return string
     */
    public function configAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("haproxy showconf");
        return array("response" => $response);
    }

    /**
     * get config diff
     * @return string
     */
    public function diffAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("haproxy configdiff");
        return array("response" => $response);
    }

    /**
     * download config file or config archive
     * @return array|mixed
     */
    public function downloadAction($type)
    {
        $backend = new Backend();

        if ($type == 'config') {
            $result = $backend->configdRun("haproxy showconf");
            $filename = 'haproxy.conf';
            $filetype = 'text/plain';
            $content = $result;
        } else {
            $result = $backend->configdRun("haproxy exportall");
            $filename = 'haproxy_config_export.zip';
            $filetype = 'application/zip';
            $content = file_get_contents('/tmp/haproxy_config_export.zip');
        }

        $response = array(
          'result' => $result,
          'filename' => $filename,
          'filetype' => $filetype,
          'content' => base64_encode($content),
        );
        return $response;
    }
}
