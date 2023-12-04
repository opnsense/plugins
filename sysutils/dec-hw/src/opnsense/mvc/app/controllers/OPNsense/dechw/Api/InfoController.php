<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\dechw\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class InfoController extends ApiControllerBase
{
    public function powerStatusAction()
    {
        $result = [
            "status" => "failed",
            "status_translated" => gettext("Power status could not be fetched.
                This widget is only applicable to Deciso hardware with dual power supplies.")
        ];
        $status = parse_ini_string((new Backend())->configdRun('dechw power'));

        if (!empty($status)) {
            $result["status"] = "OK";
            unset($result["status_translated"]);

            foreach (['pwr1', 'pwr2'] as $key) {
                $result[$key . '_translated'] = $status[$key] === '1' ? gettext('On') : gettext('Off');
            }
            $result = array_merge($result, $status);
        }

        return $result;
    }
}
