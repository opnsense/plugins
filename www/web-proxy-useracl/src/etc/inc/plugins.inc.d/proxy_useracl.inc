<?php

/**
 *    Copyright (C) 2017 Smart-Soft
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

function proxy_useracl_configure()
{
    return [
        'webproxy' => ['proxy_useracl_squid_hook:2'],
    ];
}

function proxy_useracl_squid_hook($verbose, $action)
{
    if (!in_array($action, ['restart', 'reload', 'start', 'stop'])) {
        exit;
    }

    $res = configd_run('template reload OPNsense/ProxyUserACL');
    if ($verbose) {
        printf("template reload OPNsense/ProxyUserACL: %s\n", trim($res));
    }
    $res = configd_run('proxyuseracl reload');
    if ($verbose) {
        printf("proxyuseracl reload: %s\n", trim($res));
    }
}

function proxy_useracl_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'proxyuseracl';
    $result['section'] = 'OPNsense.ProxyUserACL';
    $result['description'] = gettext('Group & User Squid ACL');
    return array($result);
}
