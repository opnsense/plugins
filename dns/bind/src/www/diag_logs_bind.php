<?php

$type = 'named';
if (isset($_GET['type']) && ($_GET['type'] === 'named' || $_GET['type'] === 'query' || $_GET['type'] === 'rpz')) {
    $type = $_GET['type'];
}

$logfile = "/var/log/named/{$type}.log";
$logclog = false;
$logsplit = 5;

$logpills = array();
$logpills[] = array(gettext('General'), true, '/diag_logs_bind.php?type=named');
$logpills[] = array(gettext('Queries'), false, '/diag_logs_bind.php?type=query');
$logpills[] = array(gettext('Blocked'), false, '/diag_logs_bind.php?type=rpz');

$service_hook = 'bind';

require_once 'diag_logs_template.inc';
