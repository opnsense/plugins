<?php

/*
 * Copyright (C) 2024 Sheridan Computers
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

namespace OPNsense\Tailscale\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class StatusController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'Tailscale';
    protected static $internalModelClass = '\OPNsense\Tailscale\Status';

    public function statusAction()
    {
        $response = json_decode(trim((new Backend())->configdRun('tailscale tailscale-status')), true);
        if ($response !== null) {
            return $response;
        }
        return ['error' => 'Unable to determine Tailscale status, is the service running?'];
    }

    public function ipAction()
    {
        $response = trim((new Backend())->configdRun('tailscale tailscale-ip'));
        return ['result' => $response];
    }

    public function netAction()
    {
        $response = trim((new Backend())->configdRun('tailscale tailscale-netcheck'));
        return ['result' => $response];
    }

    private function isJson($string)
    {
        return is_string($string)
            && (is_object(json_decode($string))
            || is_array(json_decode($string)));
    }
}
