#!/usr/local/bin/php
<?php
require_once '/usr/local/opnsense/mvc/app/config/bootstrap.php';

use OPNsense\Qfeeds\Api\SettingsController;

// Ensure the aliases directory exists
$aliasesDir = '/usr/local/opnsense/scripts/qfeeds/aliases';
if (!is_dir($aliasesDir)) {
    mkdir($aliasesDir, 0755, true);
}

// Create log file if it doesn't exist
$logFile = '/var/log/qfeeds.log';
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0644);
}

// Call the update API
$controller = new SettingsController();
$result = $controller->updateAction();

// Log the result
$date = date('c');
file_put_contents($logFile, "[$date] Cron update result: " . json_encode($result) . PHP_EOL, FILE_APPEND);

// Output the result (will be captured in system logs)
echo json_encode($result) . PHP_EOL; 