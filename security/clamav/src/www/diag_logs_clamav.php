<?php

$type = 'clamd';
if (isset($_GET['type']) && ($_GET['type'] === 'clamd' || $_GET['type'] === 'freshclam')) {
    $type = $_GET['type'];
}

$logfile = "/var/log/clamav/{$type}.log";
$logclog = false;
$logsplit = 5;

$logpills = array();
$logpills[] = array(gettext('Clamd'), true, '/diag_logs_clamav.php?type=clamd');
$logpills[] = array(gettext('Freshclam'), false, '/diag_logs_clamav.php?type=freshclam');

$service_hook = 'clamav';

require_once 'diag_logs_template.inc';
