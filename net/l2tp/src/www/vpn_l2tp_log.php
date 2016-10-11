<?php

if (htmlspecialchars($_POST['mode'])) {
    $mode = htmlspecialchars($_POST['mode']);
} elseif (htmlspecialchars($_GET['mode'])) {
    $mode = htmlspecialchars($_GET['mode']);
} else {
    $mode = 'login';
}

$logtype = 'l2tp';
$logclog = false;

$logpills = array();
$logpills[] = array(gettext('L2TP Logins'), $mode != 'raw', '/vpn_l2tp_log.php');
$logpills[] = array(gettext('L2TP Raw'), $mode == 'raw', '/vpn_l2tp_log.php?mode=raw');

$service_hook = 'l2tpd';

if ($mode != 'raw') {
    $logfile = '/var/log/vpn.log';
    require_once 'vpn_l2tp_log.inc';
} else {
    $logfile = '/var/log/l2tps.log';
    require_once 'diag_logs_template.inc';
}
