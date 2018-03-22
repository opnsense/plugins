<?php

function proxy_sso_configure()
{
    return [
        'webproxy' => ['proxy_sso_squid_hook:2'],
    ];
}

function proxy_sso_squid_hook($verbose, $action)
{
    if ($action == "reconfigure") {
        configd_run('template reload OPNsense/ProxySSO');
    }
}

function proxy_sso_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'proxysso';
    $result['section'] = 'OPNsense.ProxySSO';
    $result['description'] = gettext('Kerberos authentication module');
    return array($result);
}
