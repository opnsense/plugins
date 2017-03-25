<?php
/**
 *    Copyright (C) 2017 Fabian Franz
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Unbound\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\Cron
 */
class DiagnosticsextensionController extends ApiControllerBase
{
    /**
     * reconfigure return the stats
     */
    public function statsAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = trim($backend->configdRun('unbounddiagnostics stats'));
        if ($result != "null") {
            $ret['status'] = "ok";
            $ret['data'] = json_decode($result);
        }
        return $ret;
    }

    /**
     * return the entries of the cage
     */
    public function dumpcacheAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("unbounddiagnostics dumpcache")), true);
        if ($result !== null) {
            $ret['data'] = $result;
            $ret['status'] = 'ok';
        }
        return $ret;
    }
}
