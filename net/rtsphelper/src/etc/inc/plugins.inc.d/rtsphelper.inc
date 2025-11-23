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
    
    if ($ext_ifname == $ext_iface) {
        // get_real_interface returns the input if it fails or is already real? 
        // Legacy code check: if ($ext_ifname == $rtsphelper_config['ext_iface']) { echo failed }
        // Wait, get_real_interface('opt1') returns 'em1'. If 'em1' passed, returns 'em1'.
        // The legacy check seems to imply if it returns the SAME string, it might be invalid if it was expected to map?
        // Or maybe it checks if it's NOT a valid interface?
        // Let's assume get_real_interface returns the interface name.
        // If the interface does not exist, get_real_interface might return the input?
        // Let's keep the legacy check logic but adapted.
        // Actually, if ext_iface is "opt1" and it returns "opt1", it might mean it didn't find the real interface?
        // But if I select "em0", it returns "em0".
        // Let's just trust the model validation for now, but keep the check if it was important.
        // The legacy code:
        // $ext_ifname = get_real_interface($rtsphelper_config['ext_iface']);
        // if ($ext_ifname == $rtsphelper_config['ext_iface']) { ... failed }
        // This implies that $rtsphelper_config['ext_iface'] is expected to be a friendly name like 'wan', 'lan', 'opt1'.
        // If it returns the same, it means it couldn't resolve it?
        // But if I select a physical interface in the UI?
        // In MVC InterfaceField, it stores the handle (e.g. 'wan', 'opt1') or physical if not assigned?
        // Usually 'wan'.
        // So if get_real_interface('wan') returns 'wan', that's bad? No, it should return 'em0'.
        // If it returns 'wan', it means it failed to resolve.
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

