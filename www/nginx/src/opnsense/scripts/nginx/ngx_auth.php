<?php

/*
 * Copyright (C) 2018 Fabian Franz
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
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

require_once("config.inc");
require_once("auth.inc");
require_once("util.inc");

$uri = $_SERVER['Original-URI'];
$host = $_SERVER['Original-HOST'];
$method = $_SERVER['Original-METHOD'];
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
$server_uuid = $_SERVER['SERVER-UUID'];

function password_auth_test($username, $password, $auth_server)
{
    $authFactory = new OPNsense\Auth\AuthenticationFactory;
    $authenticator = $authFactory->get($auth_server);
    return $authenticator->authenticate($username, $password);
}

function password_auth($auth_server = 'Local Database')
{
    if (!isset($_SERVER['PHP_AUTH_PW']) || !isset($_SERVER['PHP_AUTH_USER'])) {
        return false;
    }
    return password_auth_test($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $auth_server);
}

if (empty($_SERVER['AUTH_SERVER'])) {
    $auth_server = 'Local Database';
} else {
    $auth_server = $_SERVER['AUTH_SERVER'];
}

if (password_auth($auth_server)) {
    header("HTTP/1.1 200 OK");
} else {
    header("HTTP/1.1 401 Authorization Required");
    header('WWW-Authenticate: Basic realm="OPNsense Protected Area - Authentication Required"');
}
