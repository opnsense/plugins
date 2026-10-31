<?php
/* SPDX-License-Identifier: BSD-2-Clause */
require_once('/usr/local/opnsense/mvc/script/load_phalcon.php');
require_once(__DIR__ . '/../src/opnsense/mvc/app/models/OPNsense/Newt/Newt.php');

$model = new \OPNsense\Newt\Newt();
$model->general->enabled = '1';
$model->general->endpoint = 'https://pangolin.example.com';
$model->general->siteId = 'site123';
$model->general->secret = 'example-secret';
$model->general->mtu = '1280';
$model->general->logLevel = 'INFO';
$model->general->dns = '9.9.9.9';
$model->general->port = '51830';
$messages = $model->performValidation();
if (count($messages)) {
    foreach ($messages as $message) {
        echo $message->getField() . ': ' . $message->getMessage() . "\n";
    }
    exit(1);
}
foreach ([['endpoint', 'http://insecure.example'], ['mtu', '1000'], ['port', '70000'], ['dns', 'not-an-ip'], ['siteId', 'bad;id']] as [$field, $value]) {
    $old = (string)$model->general->$field;
    $model->general->$field = $value;
    if (!count($model->performValidation())) {
        throw new RuntimeException('Accepted invalid ' . $field);
    }
    $model->general->$field = $old;
}
$model->general->port = '';
$model->general->dns = '';
if (count($model->performValidation())) {
    throw new RuntimeException('Empty optional values must be accepted.');
}
echo "Model validation checks passed.\n";
