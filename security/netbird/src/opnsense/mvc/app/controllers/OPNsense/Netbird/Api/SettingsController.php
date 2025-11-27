<?php

/*
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

/**
 * netbird settings controller
 * @package OPNsense\Netbird
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'settings';
    protected static $internalModelClass = '\OPNsense\Netbird\Settings';

    public function syncAction()
    {
        $backend = new Backend();

        $result = $backend->configdRun("netbird sync-config");
        if (stripos($result, 'done') === false) {
            return [
                'result' => 'failed to sync config: ' . $result,
            ];
        }

        $status = json_decode($backend->configdRun("netbird status-json"), true);
        $connected = $status['management']['connected'] ?? false;
        if (json_last_error() === JSON_ERROR_NONE && $connected === true) {
            $backend->configdRun("netbird down");
            $backend->configdRun("netbird up");
        }

        return ['result' => 'synced'];
    }
}
