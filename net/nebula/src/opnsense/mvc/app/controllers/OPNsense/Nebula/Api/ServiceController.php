<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

namespace OPNsense\Nebula\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Nebula\Nebula;

/**
 * Class ServiceController
 *
 * Exposes /api/nebula/service/{start,stop,restart,reconfigure,status,dirty}. The
 * base class drives each via the matching configd action (actions_nebula.conf),
 * which shells out to scripts/OPNsense/Nebula/setup.php.
 *
 * $internalServiceTemplate is intentionally NOT set: setup.php renders each
 * instance's YAML itself, so there is no template-engine target to reload (a
 * non-existent template would make reconfigureAction throw). reconfigure still
 * works via the default reconfigureForceRestart()==1 stop/start cycle.
 *
 * @package OPNsense\Nebula
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = 'OPNsense\Nebula\Nebula';
    protected static $internalServiceName = 'nebula';

    /**
     * Nebula has no global enable: each instance is its own daemon, gated by
     * its own enabled flag (mirrors WireGuard's per-server/per-client model).
     * The base class needs *some* answer to "is the service enabled?" so the
     * standard reconfigure / status / widget plumbing works — answer "yes if
     * any instance is enabled."
     */
    protected function serviceEnabled()
    {
        foreach (($this->getModel())->instances->instance->iterateItems() as $node) {
            if ((string)$node->enabled === '1') {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply pending config, then clear the subsystem-dirty marker so the "apply
     * needed" banner goes away (and stays away across reloads).
     */
    public function reconfigureAction()
    {
        $result = parent::reconfigureAction();
        if (is_array($result) && ($result['status'] ?? '') === 'ok') {
            @unlink(Nebula::RECONFIGURE_MARKER);
        }
        return $result;
    }

    /**
     * Report whether the Nebula config has unapplied changes. Consumed on page
     * load so the apply banner persists until a reconfigure (mirrors IPsec's
     * legacy_subsystem/status isDirty).
     */
    public function dirtyAction()
    {
        return ['isDirty' => file_exists(Nebula::RECONFIGURE_MARKER)];
    }
}
