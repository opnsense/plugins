<?php

/*
 * Copyright (C) 2026 Brendan Bank
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

namespace OPNsense\MetricsExporter\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'general';
    protected static $internalModelClass = 'OPNsense\MetricsExporter\MetricsExporter';

    /**
     * Retrieve available collectors with their enabled state.
     * @return array
     */
    public function collectorsAction()
    {
        $backend = new Backend();
        $response = json_decode(
            trim($backend->configdRun('metrics_exporter list-collectors')),
            true
        );
        if ($response !== null) {
            return ['collectors' => $response];
        }
        return ['collectors' => []];
    }

    /**
     * Save collector enabled states to the model.
     * @return array
     * @throws \OPNsense\Base\UserException
     */
    public function saveCollectorsAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $mdl = $this->getModel();
        $input = $this->request->getPost('collectors');

        if (!is_array($input)) {
            return ['status' => 'error', 'message' => 'Invalid input'];
        }

        $collectors = [];
        foreach ($input as $type => $enabled) {
            if (preg_match('/^[a-z_]+$/', $type)) {
                $collectors[$type] = (bool)$enabled;
            }
        }

        $mdl->collectors = json_encode($collectors);
        $valMsgs = $mdl->performValidation();
        foreach ($valMsgs as $msg) {
            if ($msg->getType() === 'error') {
                return [
                    'status' => 'error',
                    'message' => (string)$msg->getMessage(),
                ];
            }
        }

        $mdl->serializeToConfig();
        \OPNsense\Core\Config::getInstance()->save();

        return ['status' => 'ok'];
    }
}
