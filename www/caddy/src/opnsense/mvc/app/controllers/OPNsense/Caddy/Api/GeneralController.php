<?php

/**
 *    Copyright (C) 2023-2025 Cedrik Pischem
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

namespace OPNsense\Caddy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'caddy';
    protected static $internalModelClass = 'OPNsense\Caddy\Caddy';

    /**
     * Trigger a custom Caddy build using a hardcoded version and user selected modules.
     *
     * @return array JSON response with status and message.
     */
    public function buildBinaryAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('POST required')];
        }

        $mdl = new \OPNsense\Caddy\Caddy();

        $version = 'v2.10.0';

        // User-defined modules
        $userModules = (string)$mdl->general->CaddyModules;

        // Always-included default modules
        $defaultModules = [
            "github.com/caddyserver/ntlm-transport",
            "github.com/mholt/caddy-dynamicdns",
            "github.com/mholt/caddy-l4",
            "github.com/mholt/caddy-ratelimit",
            "github.com/caddy-dns/cloudflare"
        ];

        // Merge + deduplicate
        $allModules = array_filter(array_unique(array_merge(
            explode(",", $userModules),
            $defaultModules
        )));

        $modules = implode(",", $allModules);

        (new \OPNsense\Core\Backend())->configdRun("caddy build_binary {$version} {$modules}");

        return [
            'status' => 'started',
            'message' => sprintf(
                gettext("Build started for Caddy %s with modules: %s"),
                $version,
                $modules
            )
        ];
    }

    /**
     * Return the current status of the background Caddy build process.
     *
     * @return array JSON-compatible status response
     */
    public function buildStatusAction()
    {
        $response = (new \OPNsense\Core\Backend())->configdRun('caddy build_status');

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || $result === null) {
            $result = [];
        }

        return $result;
    }

}
