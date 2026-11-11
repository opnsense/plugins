#!/usr/local/bin/php
<?php

require_once('/usr/local/opnsense/mvc/script/load_phalcon.php');

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Yggdrasil\Yggdrasil;

$config = Config::getInstance();
$config->lock();

try {
    $model = new Yggdrasil();

    if ((string)$model->general->PrivateKey !== '') {
        return;
    }

    $output = (new Backend())->configdRun('yggdrasil genconf');
    $generated = json_decode($output, true);
    $key = $generated['PrivateKey'] ?? null;

    if (!is_string($key) || !preg_match('/\A[a-fA-F0-9]{128}\z/', $key)) {
        throw new RuntimeException('Yggdrasil did not return a valid private key.');
    }

    $model->general->PrivateKey = $key;
    $model->serializeToConfig();
    $config->save('Initialize Yggdrasil private key');
} finally {
    $config->unlock();
}
