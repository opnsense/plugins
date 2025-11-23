<?php

use OPNsense\RTSPHelper\General;

function rtsphelper_enabled()
{
    $model = new General();
    return (string)$model->general->enabled == '1';
}

function rtsphelper_firewall($fw)
{
    if (!rtsphelper_enabled()) {
        return;
    }

    $fw->registerAnchor('rtsphelper', 'rdr');
    $fw->registerAnchor('rtsphelper', 'fw');
}

function rtsphelper_services()
{
    $services = array();

    if (!rtsphelper_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'rtsphelper';
    $pconfig['description'] = gettext('RTSP Helper');
    $pconfig['php']['restart'] = array('rtsphelper_stop', 'rtsphelper_start');
    $pconfig['php']['start'] = array('rtsphelper_start');
    $pconfig['php']['stop'] = array('rtsphelper_stop');
    $pconfig['pidfile'] = '/var/run/rtsphelper.pid';
    $services[] = $pconfig;

    return $services;
}

function rtsphelper_start()
{
    if (!rtsphelper_enabled()) {
        return;
    }

    if (isvalidpid('/var/run/rtsphelper.pid')) {
        return;
    }

    mwexec_bg('/usr/local/bin/python3 /usr/local/opnsense/scripts/net/rtsphelper/rtsphelper.py');
}

function rtsphelper_stop()
{
    killbypid('/var/run/rtsphelper.pid', 'TERM', true);
    mwexec('/sbin/pfctl -artsphelper -Fr 2>&1 >/dev/null');
    mwexec('/sbin/pfctl -artsphelper -Fn 2>&1 >/dev/null');
}

function rtsphelper_configure()
{
    return array('bootup' => array('rtsphelper_configure_do'));
}

function rtsphelper_configure_do($verbose = false)
{
    rtsphelper_stop();

    if (!rtsphelper_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting RTSP Helper...';
        flush();
    }

    $model = new General();
    $ext_iface = (string)$model->general->ext_iface;
    $ext_ifname = get_real_interface($ext_iface);
    
    // Log a warning if interface couldn't be resolved
    // This can happen if the selected interface was deleted from the system
    if ($ext_ifname == $ext_iface) {
        syslog(LOG_WARNING, "rtsphelper: Interface '{$ext_iface}' could not be resolved to a device name");
    }

    $config_text = "ext_ifname={$ext_ifname}\n";

    /* RTSP Helper access restrictions */
    foreach ($model->permissions->permission->iterateItems() as $perm) {
        $network = (string)$perm->network;
        $port = (string)$perm->port;
        $config_text .= "allow={$network} {$port}\n";
    }

    foreach ($model->hosts->host->iterateItems() as $host) {
        $ip = (string)$host->ip;
        $port = (string)$host->port;
        $config_text .= "forward={$ip}:{$port}\n";
    }

    /* write out the configuration */
    file_put_contents('/var/etc/rtsphelper.conf', $config_text);
    rtsphelper_start();

    if ($verbose) {
        echo "done.\n";
    }
}

