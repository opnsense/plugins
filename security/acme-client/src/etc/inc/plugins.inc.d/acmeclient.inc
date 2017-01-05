<?php

/**
 *    Copyright (C) 2017 Frank Wall
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

function acmeclient_enabled()
{
    global $config;

    if (isset($config['OPNsense']['AcmeClient']['general']['enabled']) && $config['OPNsense']['AcmeClient']['general']['enabled'] == 1) {
        return true;
    }

    return false;
}

function acmeclient_firewall($fw)
{
    if (!acmeclient_enabled()) {
        return;
    }

    // TODO
    $fw->registerAnchor('acme-client/*', 'nat');
    $fw->registerAnchor('acme-client/*', 'rdr');
    $fw->registerAnchor('acme-client/*', 'fw');
}

/**
 *  register legacy service
 * @return array
 */
function acmeclient_services()
{
    if (!acmeclient_enabled()) {
        return;
    }

    global $config;
    $services = array();

    $services[] = array(
        'description' => gettext('Secure Lets Encrypt client'),
        'configd' => array(
            'restart' => array('acme-http-challenge restart'),
            'start' => array('acme-http-challenge start'),
            'stop' => array('acme-http-challenge stop'),
        ),
        'name' => 'acmeclient',
    );

    return $services;
}

/**
 *  sync configuration via xmlrpc
 * @return array
 */

/**
XXX: needs investigation, auto-renewal must be disabled on secondary node(s)
function acmeclient_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'acmeclient';
    $result['section'] = 'OPNsense.acmeclient';
    $result['description'] = gettext('Lets Encrypt Client');
    return array($result);
}
 */
