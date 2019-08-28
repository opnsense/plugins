<?php

$type = 'dnscrypt-proxy';
if (isset($_GET['type']) && ($_GET['type'] === 'dnscrypt-proxy' || $_GET['type'] === 'query' || $_GET['type'] === 'nx')) {
    $type = $_GET['type'];
}

$logfile = "/var/log/dnscrypt-proxy/{$type}.log";
$logclog = false;
$logsplit = 2;

$logpills = array();
$logpills[] = array(gettext('General'), true, '/diag_logs_dnscrypt.php?type=dnscrypt-proxy');
$logpills[] = array(gettext('Queries'), false, '/diag_logs_dnscrypt.php?type=query');
$logpills[] = array(gettext('NX'), false, '/diag_logs_dnscrypt.php?type=nx');

$service_hook = 'dnscrypt-proxy';

require_once 'diag_logs_template.inc';
