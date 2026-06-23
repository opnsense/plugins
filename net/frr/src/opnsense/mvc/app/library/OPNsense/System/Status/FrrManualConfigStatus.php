<?php

/*
 * Copyright (C) 2026 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions in the documentation and/or other
 *    materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES, LOSS OF USE, DATA, OR PROFITS, OR BUSINESS
 * INTERRUPTION HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT INCLUDING NEGLIGENCE OR OTHERWISE
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\System\Status;

use OPNsense\Quagga\General;
use OPNsense\System\AbstractStatus;
use OPNsense\System\SystemStatusCode;

class FrrManualConfigStatus extends AbstractStatus
{
    public function __construct()
    {
        $this->internalPriority = 2;
        $this->internalPersistent = true;
        $this->internalIsBanner = true;
        $this->internalTitle = gettext('FRR manual configuration');
        $this->internalScope = [
            '/ui/quagga/*'
        ];
    }

    public function collectStatus()
    {
        if (!(new General())->manual_config->isEmpty()) {
            $this->internalMessage = gettext(
                'Manual FRR configuration is enabled. Configuration file generation is disabled. Settings configured here may no longer affect the active FRR configuration.'
            );
            $this->internalStatus = SystemStatusCode::NOTICE;
        }
    }
}