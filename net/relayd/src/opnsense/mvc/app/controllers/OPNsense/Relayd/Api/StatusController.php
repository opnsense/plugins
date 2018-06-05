<?php

/**
 *    Copyright (C) 2018 EURO-LOG AG
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

namespace OPNsense\Relayd\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Relayd\Relayd;

/**
 * Class StatusController
 * @package OPNsense\Relayd
 */
class StatusController extends ApiControllerBase
{
    /**
     * get relayd summary
     */
    public function sumAction()
    {
        $result = array("result" => "failed");
        $backend = new Backend();
        $output = array();
        $output = explode("\n", trim($backend->configdRun('relayd summary')));
        if (empty($output[0])) {
            return $result;
        }
        $result["result"] = 'ok';
        $virtualServerId = 0;
        $virtualServerType = '';
        $tableId = 0;
        $virtualserver = array();
        $rows = array();
        foreach ($output as $line) {
            $words = explode("\t", $line);
            $id = trim($words[0]);
            $type = trim($words[1]);
            if ($type == 'redirect' || $type == 'relay') {
                // new virtual server id/type means new record
                if (($id != $virtualServerId && $virtualServerId > 0) ||
                    ($type != $virtualServerType && strlen($virtualServerType) > 5)) {
                    $rows[] = $virtualserver;
                    $virtualserver = array();
                }
                $virtualServerId = $id;
                $virtualServerType = $type;
                $virtualserver['id'] = $id;
                $virtualserver['type'] = $type;
                $virtualserver['name'] = trim($words[2]);
                $virtualserver['status'] = trim($words[4]);
            }
            if ($type == 'table') {
                $tableId = $id;
                $virtualserver['tables'][$tableId]['name'] = trim($words[2]);
                $virtualserver['tables'][$tableId]['status'] = trim($words[4]);
            }
            if ($type == 'host') {
                $hostId = trim($words[0]);
                $virtualserver['tables'][$tableId]['hosts'][$hostId]['name'] = trim($words[2]);
                $virtualserver['tables'][$tableId]['hosts'][$hostId]['avlblty'] = trim($words[3]);
                $virtualserver['tables'][$tableId]['hosts'][$hostId]['status'] = trim($words[4]);
            }
        }
        $rows[] = $virtualserver;
        $result["rows"] = $rows;
        return $result;
    }

    /**
     * enable/disable relayd objects
     */
    public function toggleAction($nodeType = null, $id = null, $action = null)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
        }
        $result = array("result" => "failed", "function" => "toggle");
        if ($nodeType != null &&
                ($nodeType == 'redirect' ||
                 $nodeType == 'table' ||
                 $nodeType == 'host')) {
            if ($action != null &&
                    ($action == 'enable' ||
                     $action == 'disable')) {
                if ($id != null && $id > 0) {
                    $backend = new Backend();
                    $result["output"] = $backend->configdRun("relayd toggle $nodeType $action $id");
                    if (isset($result["output"])) {
                        $result["result"] = 'ok';
                    }
                    $result["output"] = trim($result["output"]);
                }
            }
        }
        return $result;
    }
}
