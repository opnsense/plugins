<?php

function proxy_sso_configure()
{
    return [
        'webproxy_reconfigure' => ['proxy_sso_squid_reconfigure'],
    ];
}

function proxy_sso_squid_reconfigure()
{
    configd_run('template reload OPNsense/ProxySSO');
}