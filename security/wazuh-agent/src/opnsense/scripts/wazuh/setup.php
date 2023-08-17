#!/usr/local/bin/php
<?php

/**
 *    Copyright (C) 2023 Deciso B.V.
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

require_once('script/load_phalcon.php');
$authd_pass = '/var/ossec/etc/authd.pass';

$mdl = new \OPNsense\WazuhAgent\WazuhAgent();

/**
 * Configure authentication when needed
 */
if (!empty((string)$mdl->auth->password)) {
    $fhandle = fopen($authd_pass, 'a+');
    if (flock($fhandle, LOCK_EX)) {
        chown($authd_pass, 'root');
        chgrp($authd_pass, 'wazuh');
        chmod($authd_pass, 0640);
        fseek($fhandle, 0);
        ftruncate($fhandle, 0);
        fwrite($fhandle, (string)$mdl->auth->password);
        flock($fhandle, LOCK_UN);
    }
} elseif (file_exists($authd_pass)) {
    unlink($authd_pass);
}

/***
 * Temporary solution to link log files so we can view at least the last items in the file easily for debug purposes
 * It looks like ossec is not able to log to syslog directly, which means our log files live outside our normal bounds
 **/
mkdir("/var/log/wazuhagent/ossec/", 0700, true);
mkdir("/var/log/wazuhagent/activeresponses/", 0700, true);
@symlink("/var/ossec/logs/ossec.log", "/var/log/wazuhagent/ossec/ossec_99991231.log");
@symlink("/var/ossec/logs/active-responses.log", "/var/log/wazuhagent/activeresponses/activeresponses_99991231.log");
