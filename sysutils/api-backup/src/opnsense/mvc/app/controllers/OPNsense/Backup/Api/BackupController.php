<?php

/*
 * Copyright (C) 2023 Frank Wall
 * Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Backup\Api;

use OPNsense\Base\ApiControllerBase;

class BackupController extends ApiControllerBase
{
    const CONFIG_XML = '/conf/config.xml';

    /**
     * download system config
     * @param string $format set to 'json' to get a base64 encoded config backup
     * @return array|mixed
     */
    public function downloadAction($format = 'plain')
    {
        $data = file_get_contents(self::CONFIG_XML);
        $status = $data === false ? 'error' : 'success';

        if ($format == 'json') {
            $response = array(
              'status' => $status,
              'filename' => 'config.xml',
              'filetype' => 'application/xml',
              'content' => base64_encode($data),
            );
            return $response;
        } else {
            $this->response->setStatusCode(200, "OK");
            $this->response->setContentType('application/xml', 'UTF-8');
            $this->response->setHeader("Content-Disposition", "attachment; filename=\"config.xml\"");
            $data = file_get_contents(self::CONFIG_XML);
            $this->response->setContent($data);
        }
    }

    /**
     * process API results, serialize return data to json.
     * @param $dispatcher
     * @return string json data
     */
    public function afterExecuteRoute($dispatcher)
    {
        // check if reponse headers are already set
        if ($this->response->getHeaders()->get("Status") != null) {
            // Headers already set, send unmodified response.
        } else {
            // process response, serialize to json object
            $data = $dispatcher->getReturnedValue();
            if (is_array($data)) {
                $this->response->setContentType('application/json', 'UTF-8');
                if ($this->isExternalClient()) {
                    $this->response->setContent(json_encode($data));
                } else {
                    $this->response->setContent(htmlspecialchars(json_encode($data), ENT_NOQUOTES));
                }
            }
        }

        return $this->response->send();
    }
}
