<?php

/*
 * Copyright (C) 2026 VEQNORA
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

namespace OPNsense\PPPoEServer\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Class DiagnosticsController read-only diagnostics for the PPPoE server
 * @package OPNsense\PPPoEServer\Api
 */
class DiagnosticsController extends ApiControllerBase
{
    /**
     * run a fixed diagnostics action and pass its JSON through
     * @param string $action configd action name
     * @return array
     */
    private function jsonAction($action)
    {
        $response = (new Backend())->configdRun('pppoe_server ' . $action);
        $payload = json_decode((string)$response, true);
        return is_array($payload) ? $payload : ['status' => 'failed'];
    }

    public function versionsAction()
    {
        return $this->jsonAction('versions');
    }

    public function validateAction()
    {
        return $this->jsonAction('validate');
    }

    public function netgraphAction()
    {
        return $this->jsonAction('netgraph_status');
    }

    public function configPreviewAction()
    {
        return $this->jsonAction('config_preview');
    }

    public function poolStatusAction()
    {
        return $this->jsonAction('pool_status');
    }

    public function supportBundleAction()
    {
        return $this->jsonAction('support_bundle');
    }

    /**
     * probe all configured RADIUS servers (POST: sends network traffic)
     * @return array
     */
    public function radiusTestAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => gettext('Invalid request.')];
        }
        return $this->jsonAction('radius_test');
    }

    /**
     * Prometheus text exposition
     * @return string
     */
    public function metricsAction()
    {
        $response = (new Backend())->configdRun('pppoe_server metrics');
        $this->response->setContentType('text/plain', 'UTF-8');
        $this->response->setContent((string)$response);
        return $this->response;
    }
}
