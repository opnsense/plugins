<?php

/*
 * Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 * Copyright (C) 2025 squared GmbH
 * Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
 * Copyright (C) 2025 NetBird GmbH
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

namespace OPNsense\Netbird\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Netbird\Authentication;

/**
 * netbird authentication controller
 * @package OPNsense\Netbird
 */
class AuthenticationController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'authentication';
    protected static $internalModelClass = '\OPNsense\Netbird\Authentication';

    public function getAction(): array
    {
        $mdl = new Authentication();

        $managementUrl = $mdl->managementUrl->__toString();
        $setupKey = $mdl->setupKey->__toString();

        $defaultKey = '00000000-0000-0000-0000-000000000000';
        if (!empty($setupKey) && $setupKey !== $defaultKey) {
            $visiblePart = substr($setupKey, 0, 4);
            $maskedKey = $visiblePart . str_repeat('*', max(4, strlen($setupKey) - 4));
        }else{
            $maskedKey = $defaultKey;
        }

        return [
            'authentication' => [
                'managementUrl' => $managementUrl,
                'setupKey' => $maskedKey
            ]
        ];
    }

    public function upAction()
    {
        $backend = new Backend();
        $mdl = new Authentication();

        $status = json_decode($backend->configdRun("netbird status-json"), true);
        $connected = $status['management']['connected'] ?? false;

        if (json_last_error() === JSON_ERROR_NONE && $connected === true) {
            $backend->configdRun("netbird down");
        }

        $managementUrl = $mdl->managementUrl->__toString();
        $setupKey = $mdl->setupKey->__toString();

        $result = $backend->configdpRun("netbird up-setup-key", array($managementUrl, $setupKey));
        return ['result' => trim($result)];
    }

    public function downAction(): array
    {
        $backend = new Backend();

        $status = json_decode($backend->configdRun("netbird status-json"), true);
        $connected = $status['management']['connected'] ?? false;

        if (json_last_error() === JSON_ERROR_NONE && $connected === true) {
            $result = $backend->configdRun("netbird down");
            return ['result' => trim($result)];
        }
        return ['result' => 'already disconnected or not running'];
    }
}
