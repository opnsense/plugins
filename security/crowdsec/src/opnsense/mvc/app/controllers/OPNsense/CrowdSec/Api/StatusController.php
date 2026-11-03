<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2026 Michael Pihlblad

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\CrowdSec\General;

/**
 * Connection of the remediation component to its LAPI
 * @package OPNsense\CrowdSec
 */
class StatusController extends ApiControllerBase
{
    /**
     * Whether a remote LAPI API key is stored, without revealing it
     *
     * @return array
     */
    public function keyAction(): array
    {
        return ['set' => !(new General())->remote_bouncer_api_key->isEmpty()];
    }

    /**
     * Check that the bouncer can reach its LAPI with its API key
     *
     * @return array result (ok, invalid_key, unreachable, error) and message
     */
    public function testAction(): array
    {
        if (!$this->request->isPost()) {
            $this->response->setStatusCode(405, "Method Not Allowed");
            $this->response->setHeader("Allow", "POST");
            return [];
        }

        $result = json_decode(trim((new Backend())->configdRun('crowdsec bouncer-test')), true);
        if (!is_array($result)) {
            return ['result' => 'error', 'message' => gettext('The connection test did not return a result.')];
        }

        return $result;
    }
}
