<?php

if (htmlspecialchars($_POST['mode'])) {
    $mode = htmlspecialchars($_POST['mode']);
} elseif (htmlspecialchars($_GET['mode'])) {
    $mode = htmlspecialchars($_GET['mode']);
} else {
    $mode = 'login';
}

$logtype = 'poes';
$logclog = false;

$logpills = array();
$logpills[] = array(gettext('PPPoE Logins'), $mode != 'raw', '/vpn_pppoe_log.php');
$logpills[] = array(gettext('PPPoE Raw'), $mode == 'raw', '/vpn_pppoe_log.php?mode=raw');

if ($mode != 'raw') {
    $logfile = '/var/log/vpn.log';
    require_once 'vpn_pppoe_log.inc';
} else {
    $logfile = '/var/log/poes.log';
    require_once 'diag_logs_template.inc';
}
