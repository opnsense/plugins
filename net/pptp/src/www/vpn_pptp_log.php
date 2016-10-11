<?php

if (htmlspecialchars($_POST['mode'])) {
    $mode = htmlspecialchars($_POST['mode']);
} elseif (htmlspecialchars($_GET['mode'])) {
    $mode = htmlspecialchars($_GET['mode']);
} else {
    $mode = 'login';
}

$logtype = 'pptp';
$logclog = false;

$logpills = array();
$logpills[] = array(gettext('PPTP Logins'), $mode != 'raw', '/vpn_pptp_log.php');
$logpills[] = array(gettext('PPTP Raw'), $mode == 'raw', '/vpn_pptp_log.php?mode=raw');

$service_hook = 'pptpd';

if ($mode != 'raw') {
    $logfile = '/var/log/vpn.log';
    require_once 'vpn_pptp_log.inc';
} else {
    $logfile = '/var/log/pptps.log';
    require_once 'diag_logs_template.inc';
}
