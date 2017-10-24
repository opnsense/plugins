<?php

function proxy_useracl_configure()
{
    return [
        'webproxy' => ['proxy_useracl_squid_hook:2'],
    ];
}

function proxy_useracl_squid_hook()
{
    configd_run('template reload OPNsense/ProxyUserACL');
    configd_run('proxyuseracl reconfigure');
}
