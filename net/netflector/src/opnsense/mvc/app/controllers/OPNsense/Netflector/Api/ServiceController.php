<?php

/*
 * Copyright (C) 2026 Sergii Bogomolov
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

namespace OPNsense\Netflector\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Netflector\Netflector;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Netflector\Netflector';
    protected static $internalServiceTemplate = 'OPNsense/Netflector';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'netflector';

    /**
     * The base class reads a single model path, but the daemon refuses to start with no reflector to
     * run. "Enabled" therefore has to mean the same thing here as in netflector_enabled() and in the
     * rc.conf.d template: the service switch is on AND at least one entry is on. That makes Apply stop
     * the daemon once the last reflector goes, rather than starting one on a configuration it would
     * reject, and the status widget reports "disabled" for the same reason.
     */
    protected function serviceEnabled()
    {
        $model = new Netflector();

        if ((string)$model->general->enabled !== '1') {
            return false;
        }
        foreach ($model->reflectors->reflector->iterateItems() as $entry) {
            if ((string)$entry->enabled === '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * Ask the daemon itself whether the generated configuration is valid (netflector --check-config).
     * The model's rules mirror the daemon's, but a mirror can drift; this is the authority, and it runs
     * against the file that will actually be loaded.
     */
    public function checkAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => gettext('This endpoint expects a POST.')];
        }

        // The action renders the configuration into a throwaway root and validates that, so it reports on
        // what would be applied without rewriting the file a restart would load. Regenerating the live
        // file here instead would mean a failed validation left the daemon unable to come back up.
        //
        // It answers as JSON with three states, because "valid", "invalid" and "nothing is enabled" are
        // genuinely different answers and only the middle one is a problem.
        $backend = new Backend();
        $output = trim($backend->configdRun('netflector check'));

        $result = json_decode($output, true);
        if (!is_array($result) || !isset($result['status'])) {
            return [
                'status' => 'failed',
                'message' => $output !== '' ? $output : gettext('The validation returned nothing.'),
            ];
        }

        return $result;
    }
}
