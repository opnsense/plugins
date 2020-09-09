<?php

function rtsphelper_enabled()
{
    global $config;

    return isset($config['installedpackages']['rtsphelper']['config'][0]['enable']);
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

function rtsphelper_permuser_list()
{
    $ret = array();
    $count = 3;

    for ($i = 1; $i <= $count; $i++) {
        $ret[$i] = "permuser{$i}";
    }

    return $ret;
}

function rtsphelper_forward_list()
{
    $ret = array();
    $count = 5;

    for ($i = 1; $i <= $count; $i++) {
        $ret[$i] = "forward{$i}";
    }

    return $ret;
}

function rtsphelper_configure_do($verbose = false)
{
    global $config;

    rtsphelper_stop();

    if (!rtsphelper_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting RTSP Helper...';
        flush();
    }

    $rtsphelper_config = $config['installedpackages']['rtsphelper']['config'][0];

    $ext_ifname = get_real_interface($rtsphelper_config['ext_iface']);
    if ($ext_ifname == $rtsphelper_config['ext_iface']) {
        if ($verbose) {
            echo "failed.\n";
        }
        return;
    }

    $config_text = "ext_ifname={$ext_ifname}\n";

    $ifaces_active = '';

    /* RTSP Helper access restrictions */
    foreach (rtsphelper_permuser_list() as $permuser) {
        if (!empty($rtsphelper_config[$permuser])) {
            $config_text .= "allow={$rtsphelper_config[$permuser]}\n";
        }
    }

    foreach (rtsphelper_forward_list() as $forward) {
        if (!empty($rtsphelper_config[$forward])) {
            $config_text .= "forward={$rtsphelper_config[$forward]}\n";
        }
    }
    /* write out the configuration */
    file_put_contents('/var/etc/rtsphelper.conf', $config_text);
    rtsphelper_start();

    if ($verbose) {
        echo "done.\n";
    }
}
