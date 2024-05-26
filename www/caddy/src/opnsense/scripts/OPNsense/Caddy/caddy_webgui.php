#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2024 Cedrik Pischem
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
 */

require_once("config.inc");
use OPNsense\Core\Config;

$configObj = Config::getInstance()->object();

function check_webgui_settings($configObj)
{
    $system = $configObj->system;

    if (empty($system->webgui)) {
        return json_encode([
            'error' => gettext('WebGUI configuration is missing.')
        ]);
    }

    $webgui = $system->webgui;
    $port = !empty($webgui->port) ? (string) $webgui->port : '';
    $disablehttpredirect = isset($webgui->disablehttpredirect) ? (string) $webgui->disablehttpredirect : null;

    $errorMessages = [];
    if (empty($port) || in_array($port, ['80', '443'], true)) {
        $errorMessages[] = gettext('Change "TCP port" to a non-standard port, e.g., 8443.');
    }
    if ($disablehttpredirect === null || $disablehttpredirect === '0') {
        $errorMessages[] = gettext('Enable the checkbox "HTTP Redirect - Disable web GUI redirect rule".');
    }

    $content = [
        'port' => $port,
        'disablehttpredirect' => $disablehttpredirect
    ];

    // Error
    if (!empty($errorMessages)) {
        return json_encode([
            'error' => gettext('Caddy can not start until conflicts with the OPNsense WebGUI settings have been resolved. Go to System -> Settings -> Administration: ') . implode(' ', $errorMessages),
            'content' => $content
        ]);
    }

    // Success
    return json_encode([
        'success' => gettext('There are no conflicts between Caddy and the OPNsense WebGUI settings.'),
        'content' => $content
    ]);
}

$result = check_webgui_settings($configObj);
echo $result;
