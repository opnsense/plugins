<?php

/**
 *    Copyright (C) 2025 Cedrik Pischem
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

namespace OPNsense\XCaddy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'xcaddy';
    protected static $internalModelClass = 'OPNsense\XCaddy\XCaddy';

    /**
     * Trigger a Caddy build via configd, using the pre-generated configuration file.
     *
     * @return array JSON response
     */
    public function buildBinaryAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('POST required')];
        }

        $backend = new \OPNsense\Core\Backend();

        // Regenerate the build configuration file
        $backend->configdRun("template reload OPNsense/XCaddy");

        // Trigger the Caddy build process with build configuration file
        $response = $backend->configdRun("xcaddy build_binary");

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || $result === null) {
            $result = [];
        }

        return $result;
    }

    /**
     * Return the current status of the background Caddy build process.
     *
     * @return array JSON response
     */
    public function buildStatusAction()
    {
        $response = (new \OPNsense\Core\Backend())->configdRun('xcaddy build_status');

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || $result === null) {
            $result = [];
        }

        return $result;
    }

    /**
     * Return the current status of the background Caddy build process.
     *
     * @return array JSON response
     */
    public function updateModulesAction()
    {
        $response = (new \OPNsense\Core\Backend())->configdRun('xcaddy update_modules');

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || $result === null) {
            $result = [];
        }

        return $result;
    }

}
