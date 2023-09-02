<?php

/**
 *    Copyright (C) 2023 Thomas Rogdakis <thomas@rogdakis.com>
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

use OPNsense\Backup\Local;
use OPNsense\Core\Config;

$local = new Local();
$config = Config::getInstance()->object();
$gitSettings = $config->system?->backup?->git;

if ($gitSettings === null) {
  syslog(LOG_ERR, 'git-backup: failed to fetch git backup settings from config');
  exit(1);
}

$password = $gitSettings->encryption_password;

// if the password is empty, we don't want to encrypt the config
if ($password === null || mb_strlen($password) === 0) {
  exit(0);
}

$data = file_get_contents('/conf/backup/git/config.xml');
$encryptedData = $local->encrypt($data, $password);

file_put_contents('/conf/backup/git/config.xml', $encryptedData);