<?php

/*
 * Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 * Copyright (C) 2025 squared GmbH
 * Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
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

namespace OPNsense\netbird\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\netbird\Initial;
use OPNsense\netbird\Netbird;


/**
 * Class ServiceController
 * @package OPNsense\netbird
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    const NETBIRD_CONFIG_JSON = '/usr/local/etc/netbird/config.json';
    protected static $internalServiceClass = '\OPNsense\netbird\Netbird';
    protected static $internalServiceEnabled = 'general.Enabled';
    protected static $internalServiceTemplate = 'OPNsense/netbird';
    protected static $internalServiceName = 'netbird';

    public function conStatusAction(): string
    {
        $backend = new Backend();
        $bckResult = $backend->configdRun("netbird con-status");
        if ($bckResult !== null) {
            return nl2br(htmlspecialchars($bckResult));
        }
        return "Error retrieving connection status";
    }

    public function searchFilter($array, $value): bool
    {
        foreach ($array as $val) {
            if (str_contains(strval($val), strtolower($value))) {
                return true;
            }
        }
        return false;
    }

    public function upDownStatusAction(): string
    {
        $backend = new Backend();
        $bckResult = $backend->configdRun("netbird status");
        if (!str_contains($bckResult, "is running")) {
            return json_encode(array('updown' => "NOT RUNNING", 'status' => "Netbird is not running"));
        }
        $bckResult = $backend->configdRun("netbird short-con-status");
        $txtStatus = nl2br(htmlspecialchars($bckResult));
        $bckResult = $backend->configdRun("netbird con-status-json");
        $status = json_decode($bckResult, true);
        if (!$status['publicKey']) {
            return json_encode(array('updown' => "DOWN", 'status' => $txtStatus));
        }
        return json_encode(array('updown' => "UP", 'status' => $txtStatus));
    }

    public function searchAction(): string
    {
        $request = $this->request;
        $backend = new Backend();
        $bckResult = $backend->configdRun("netbird status");
        if (!str_contains($bckResult, "is running")) {
            return json_encode(array('current' => 1, 'rowCount' => 0, 'total' => 0, 'rows' => array()));
        }
        $bckResult = $backend->configdRun("netbird con-status-json");
        $status = json_decode($bckResult, true);
        $itemsPerPage = $request->get('rowCount', 'int', -1);
        $currentPage = $request->get('current', 'int', 1);
        $sortBy = array('status');
        $sortDescending = false;


        $searchPhrase = strtolower($request->get('searchPhrase', 'string', ''));
        if (!$status['peers']['details']) {
            return json_encode(array('current' => 1, 'rowCount' => 0, 'total' => 0, 'rows' => array()));
        }
        $details = $status['peers']['details'];
        $details = array_filter($details, function ($item) use ($searchPhrase) {
            return $this->searchFilter($item, $searchPhrase);
        });
        $detailsFlat = array();
        foreach ($details as $detail) {
            $detailsFlat[] = $this->flattenOneLevel($detail);
        }
        if ($request->hasPost('sort') && is_array($request->get("sort")) && !empty($request->get("sort"))) {
            $sortBy = array_keys($request->get("sort"));
            if (!empty($sortBy) && $request->get("sort")[$sortBy[0]] == "desc") {
                $sortDescending = true;
            }

        }
        $sortValues = array();
        foreach ($detailsFlat as $detail) {
            $sortValues[] = $detail[$sortBy[0]];
        }
        array_multisort($sortValues, $sortDescending ? SORT_DESC : SORT_ASC, $detailsFlat);
        $page = array_slice($detailsFlat, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
        $page = $this->convertFieldsToDisplay($page);
        $result = array('current' => $currentPage, 'rowCount' => count($page), 'total' => count($detailsFlat), 'rows' => $page);
        return json_encode($result);
    }

    private function flattenOneLevel($array): array
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) {
                    if ($key == "routes") {
                        $result[$key] = implode("<br />", $value);
                    }
                    else {
                        $result[$key . "." . $subkey] = $subvalue;
                    }
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function setUpAction(): string
    {
        $backend = new Backend();
        try {
            return $backend->configdRun("netbird set-up");
        } catch (\Exception $e) {
            return "Error running netbird up" . "\n" . $e->getMessage();
        }
    }

    public function initialUpAction(): string
    {
        $backend = new Backend();
        $mdlInitial = new Initial();
        $key = $mdlInitial->initial->setupkey->__toString();
        $api = $mdlInitial->initial->mgmtservice->__toString();
        $hostname = $mdlInitial->initial->hostname->__toString();
        if ($hostname == "") {
            $hostname = gethostname();
            if(!$hostname){
                $hostname = "OPNsense";
            }else{
                if(str_contains($hostname, ".")){
                    $hostname = explode(".", $hostname)[0];
                }
            }

            $mdlInitial->initial->hostname = $hostname;
        }
        $mdlInitial->initial->setupkey = "00000000-0000-0000-0000-000000000000";
        $mdlInitial->initial->initsure = 0;

        $mdlInitial->serializeToConfig();
        $cnf = Config::getInstance();
        $cnf->save();

        $bckresult = $backend->configdRun("netbird set-up-initial " . escapeshellarg($api) . " " . escapeshellarg($key) . " " . escapeshellarg($hostname));
        return nl2br(htmlspecialchars($bckresult));
    }

    public function setDownAction(): string
    {
        $backend = new Backend();
        try {
            return $backend->configdRun("netbird set-down");
        } catch (\Exception $e) {
            return "Error running netbird down" . "\n" . $e->getMessage();
        }
    }

    public function reloadAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            try {
                $mdlNetbird = new Netbird();
                $backend = new Backend();
                if (trim($backend->configdRun('template reload OPNsense/netbird')) == "OK") {
                    $status = "ok";
                }

                $enabled = $mdlNetbird->general->Enabled->__toString() == 1;
                $carpEnabled = $mdlNetbird->general->CarpIf->__toString() != '';
                $disableClientRoutes = $mdlNetbird->general->DisableClientRoutes->__toString() == 1;
                $disableServerRoutes = $mdlNetbird->general->DisableServerRoutes->__toString() == 1;
                $disableDNS = $mdlNetbird->general->DisableDNS->__toString() == 1;
                $rpEnabled = $mdlNetbird->general->QuantumEnabled->__toString() == 1;
                $rpPermissive = $mdlNetbird->general->QuantumPermissive->__toString() == 1;
                $wgPort = $mdlNetbird->general->WgPort->__toString();
                $netbirdConfigJson = file_get_contents(self::NETBIRD_CONFIG_JSON);
                $netbirdConfig = json_decode($netbirdConfigJson, true);
                $netbirdConfig["DisableAutoConnect"] = $carpEnabled;
                $netbirdConfig["DisableClientRoutes"] = $disableClientRoutes;
                $netbirdConfig["DisableServerRoutes"] = $disableServerRoutes;
                $netbirdConfig["DisableDNS"] = $disableDNS;
                $netbirdConfig["RosenpassEnabled"] = $rpEnabled;
                $netbirdConfig["RosenpassPermissive"] = $rpPermissive;
                $netbirdConfig["WgPort"] = intval($wgPort);
                $netbirdConfigJson = json_encode($netbirdConfig);
                file_put_contents(self::NETBIRD_CONFIG_JSON, $netbirdConfigJson);
                $action = $enabled ? "restart" : "stop";
                $backend->configdRun("netbird $action");
            } catch (\Exception $e) {
                $status = "failed";
                syslog(LOG_ERR, "netbird: failed to reload configuration: " . $e->getMessage());
            }
        }
        return array("status" => $status);
    }

    /**
     * @param array $page
     * @return array
     */
    public function convertFieldsToDisplay(array $page): array
    {
        for ($i = 0; $i < count($page); $i++) {
            $page[$i]['latency'] = round($page[$i]['latency'] / 1000000, 2) . " ms";
            $received = $page[$i]['transferReceived'];
            $rcvUnit = "KiB";
            $received /= 1024;
            if ($received > 1024) {
                $received /= 1024;
                $rcvUnit = "MiB";
            }
            if ($received > 1024) {
                $received /= 1024;
                $rcvUnit = "GiB";
            }

            $sent = $page[$i]['transferSent'];
            $sentUnit = "KiB";
            $sent /= 1024;
            if ($sent > 1024) {
                $sent /= 1024;
                $sentUnit = "MiB";
            }
            if ($sent > 1024) {
                $sent /= 1024;
                $sentUnit = "GiB";
            }
            $page[$i]['transferReceived'] = round($received, 2) . " " . $rcvUnit;
            $page[$i]['transferSent'] = round($sent, 2) . " " . $sentUnit;
            $page[$i]['lastStatusUpdate'] = date("Y-m-d H:i:s", strtotime($page[$i]['lastStatusUpdate']));
            $page[$i]['lastWireguardHandshake'] = date("Y-m-d H:i:s", strtotime($page[$i]['lastWireguardHandshake']));
            foreach ($page[$i] as $key => $value) {
                if ($value == "true") {
                    $page[$i][$key] = 1;
                } elseif ($value == "false") {
                    $page[$i][$key] = 0;
                }

            }
        }
        return $page;
    }
}
