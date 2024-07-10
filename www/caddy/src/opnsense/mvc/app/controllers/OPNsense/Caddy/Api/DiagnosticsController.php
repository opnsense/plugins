<?php

/**
 *    Copyright (C) 2024 Cedrik Pischem
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

namespace OPNsense\Caddy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class DiagnosticsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'caddy';
    protected static $internalModelClass = 'OPNsense\Caddy\Caddy';

    /**
     * Fetch and display the contents of the Caddy configuration as JSON.
     * Any errors are handled by caddy_diagnostics script and passed to this controller as JSON.
     */
    public function configAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('caddy config');

        // Decode JSON to PHP array
        $responseArray = json_decode($response, true);

        // Errors are handled by the caddy_diagnostics script and returned, check for an error key in the response
        if (isset($responseArray['error'])) {
            return ["status" => "failed", "message" => $responseArray['message']];
        }

        // Prepare the response array
        $response = ['status' => 'success', 'content' => $responseArray];
        // Set the content type
        $this->response->setContentType('application/json', 'UTF-8');
        // Encode and set the content
        $this->response->setContent(json_encode($response, JSON_PRETTY_PRINT));
    }

    /**
     * Fetch and display the contents of the Caddyfile as JSON.
     */
    public function caddyfileAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('caddy caddyfile');

        // Decode JSON to PHP array
        $responseArray = json_decode($response, true);

        if (isset($responseArray['error'])) {
            return ["status" => "failed", "message" => $responseArray['message']];
        }

        // Return the response as an array which gets automatically encoded to JSON
        return ["status" => "success", "content" => $responseArray['content']];
    }

    /**
     * Fetch the hostnames, validity and expiration dates of automatic certificates as JSON. Consumed by Caddy widget.
     */
    public function certificateAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('caddy certificate');

        // Decode JSON to PHP array
        $responseArray = json_decode($response, true);

        if (isset($responseArray['error'])) {
            return ["status" => "failed", "message" => $responseArray['message']];
        }

        // Return the response as an array which gets automatically encoded to JSON
        return ["status" => "success", "content" => $responseArray];
    }
}
