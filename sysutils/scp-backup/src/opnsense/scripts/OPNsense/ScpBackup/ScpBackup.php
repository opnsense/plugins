#!/usr/local/bin/php
<?php

/**
 *    Copyright (C) 2018 David Harrigan
 *    Copyright (C) 2015 - 2017 Deciso B.V.
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

require_once("util.inc");
require_once("config.inc");

use OPNsense\Base;
use OPNsense\Core\Config;

$config = Config::getInstance()->object();
$scpBackup = $config->OPNsense->ScpBackup;

if (isset($scpBackup) && isset($scpBackup->enabled) && $scpBackup->enabled == 1) {
    $hostname = escapeshellarg($scpBackup->hostname);
    $username = escapeshellarg($scpBackup->username);
    $port = $scpBackup->port;
    $remoteDirectory = empty(trim($scpBackup->remotedirectory)) ? "./" : $scpBackup->remotedirectory;
    $identifyFile = "/conf/sshd/ssh_host_rsa_key";
    $configFile = "/conf/config.xml";

    if (!substr($remoteDirectory, -1) == "/") {
        $remoteDirectory = $remoteDirectory . "/";
    }

    $remoteDirectoryFullPath = escapeshellarg($remoteDirectory . "config-" . date('Y-m-d-H-i') . ".xml");

    $command = "scp -P $port -i $identifyFile $configFile $username@$hostname:$remoteDirectoryFullPath";

    syslog(LOG_WARNING, "scp_backup command: $command");

    exec(escapeshellcmd($command), $output, $returnCode);

    if ($returnCode != 0) {
        syslog(LOG_ERR, "scp_backup command: return code [$returnCode]");
    }
}
