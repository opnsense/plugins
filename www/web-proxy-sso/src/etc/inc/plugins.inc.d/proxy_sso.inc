<?php

function proxy_sso_configure()
{
    return [
        'webproxy' => ['proxy_sso_squid_hook:2'],
    ];
}

function proxy_sso_squid_hook($verbose, $action)
{
    if (!in_array($action, ['restart', 'reload', 'start', 'stop'])) {
        exit;
    }

    $res = configd_run('template reload OPNsense/ProxySSO');
    if ($verbose) {
        printf("template reload OPNsense/ProxySSO: %s\n", trim($res));
    }
}
