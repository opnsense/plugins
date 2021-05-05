<?php

/**
 *    Copyright (C) 2018 EURO-LOG AG
 *    Copyright (c) 2021 Deciso B.V.
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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Relayd\Relayd;

/**
 * Class StatusController
 * @package OPNsense\Relayd
 */
class StatusController extends ApiControllerBase
{
    /**
     * get relayd summary
     */
    public function sumAction($wait=0)
    {
        $result = array("result" => "failed");
        $backend = new Backend();
        $relaydMdl = new Relayd();

        // when $wait is set, try for max 10 seconds to receive a sensible status (wait for unknowns to resolve)
        $max_tries = !empty($wait) ? 10 : 1;
        $output = array();
        for ($i = 0; $i < $max_tries; $i++) {
            $output = explode("\n", trim($backend->configdRun('relayd summary')));
            $unknowns = 0;
            foreach ($output as $line) {
                if (substr($line, -strlen("unknown")) == "unknown") {
                    $unknowns++;
                }
            }
            if (!empty($output[0]) && $unknowns == 0) {
                break;
            }
            sleep(1);
        }
        if (empty($output[0])) {
            return $result;
        }
        $output[] = "0\t****\t"; // end of data marker
        $result["result"] = 'ok';
        $virtualServerId = 0;
        $virtualServerType = '';
        $tableId = 0;
        $virtualserver = array();
        $rows = array();
        foreach ($output as $line) {
            $words = array_map('trim', explode("\t", $line));
            $id = $words[0];
            $type = $words[1];
            if ($type == 'redirect' || $type == 'relay' || $type == '****') {
                // new virtual server id/type means new record
                if (
                    ($id != $virtualServerId && $virtualServerId > 0) ||
                    ($type != $virtualServerType && strlen($virtualServerType) > 5) ||
                    ($type == '****' && !empty($virtualserver))
                ) {
                    // append backend hosts not found in the list, since relayd only supports disabled tables
                    // you might loose track of hosts that are disabled
                    if (!empty($virtualserver['tables'])) {
                        foreach ($virtualserver['tables'] as &$table) {
                            if (!empty($table['uuid'])) {
                                $tblnode = $relaydMdl->getNodeByReference("table.".$table['uuid']);
                                foreach (explode(",", (string)$tblnode->hosts) as $host_uuid) {
                                    $found = false;
                                    if (!empty($table['hosts'])) {
                                        foreach ($table['hosts'] as $tblhost) {
                                            if (isset($tblhost['uuid']) && $tblhost['uuid'] == $host_uuid) {
                                                $found = true;
                                            }
                                        }
                                    } else {
                                        $table['hosts'] = [];
                                    }
                                    if (!$found) {
                                        $hostnode = $relaydMdl->getNodeByReference("host.".$host_uuid);
                                        $table['hosts'][$host_uuid] = [
                                            "name" => (string)$hostnode->name,
                                            "avlblty" => null,
                                            "status" => !empty((string)$hostnode->enabled) ? "disabled" : "-"
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    $rows[] = $virtualserver;
                    if ($type == '****') {
                        break; // end
                    }
                    $virtualserver = [];
                }
                $virtualServerId = $id;
                $virtualServerType = $type;
                $virtualserver['id'] = $id;
                $virtualserver['type'] = $type;
                $virtualserver['name'] = $words[2];
                $virtualserver['status'] = $words[4];
                $obj = $relaydMdl->getObjectByName("virtualserver", $virtualserver['name']);
                if ($obj != null) {
                    $virtualserver['uuid'] = $obj->getAttribute('uuid');
                    $virtualserver['listen_address'] = (string)$obj->listen_address;
                    $virtualserver['listen_startport'] = (string)$obj->listen_startport;
                    $virtualserver['listen_endport'] = (string)$obj->listen_endport;
                }
            } elseif ($type == 'table') {
                $tableId = $id;
                if (empty($virtualserver['tables'])) {
                    $virtualserver['tables'] = [];
                }
                $virtualserver['tables'][$tableId] = [];
                $virtualserver['tables'][$tableId]['name'] = $words[2];
                $virtualserver['tables'][$tableId]['status'] = $words[4];
                $obj = $relaydMdl->getObjectByName("table", explode(":", $words[2])[0]);
                if ($obj != null) {
                    $virtualserver['tables'][$tableId]['uuid'] = $obj->getAttribute('uuid');
                }
            } elseif ($type == 'host') {
                $hostId = trim($words[0]);
                if (empty($virtualserver['tables'][$tableId]['hosts'])) {
                    $virtualserver['tables'][$tableId]['hosts'] = [];
                }
                $virtualserver['tables'][$tableId]['hosts'][$hostId] = [];
                $virtualserver['tables'][$tableId]['hosts'][$hostId]['name'] = $words[2];
                $virtualserver['tables'][$tableId]['hosts'][$hostId]['avlblty'] = $words[3];
                $virtualserver['tables'][$tableId]['hosts'][$hostId]['status'] = $words[4];
                $obj = $relaydMdl->getObjectByName("host", $words[2]);
                if ($obj != null) {
                    $virtualserver['tables'][$tableId]['hosts'][$hostId]['uuid'] = $obj->getAttribute('uuid');
                }
            }
        }
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
        if (
            $nodeType != null &&
                ($nodeType == 'redirect' ||
                 $nodeType == 'table' ||
                 $nodeType == 'host')
        ) {
            if (
                $action != null &&
                    ($action == 'enable' ||
                     $action == 'disable')
            ) {
                if ($id != null && $id > 0) {
                    $backend = new Backend();
                    $result["output"] = $backend->configdpRun("relayd toggle",[$nodeType, $action, $id]);
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
