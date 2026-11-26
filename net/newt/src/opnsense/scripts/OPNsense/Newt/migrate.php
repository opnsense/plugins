#!/usr/local/bin/php
<?php
/* SPDX-License-Identifier: BSD-2-Clause */
require_once('/usr/local/opnsense/mvc/script/load_phalcon.php');

use OPNsense\Core\Config;
use OPNsense\Newt\Newt;

$config = Config::getInstance();
$config->lock();
try {
    if (isset($config->object()->OPNsense->Newt->general)) {
        echo "Existing Newt plugin settings retained.\n";
        return;
    }
    $model = new Newt();
    $path = '/usr/local/etc/newt/config.json';
    if (is_file($path)) {
        $old = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!empty($old['endpoint']) && !empty($old['id']) && !empty($old['secret'])) {
            $model->general->endpoint = $old['endpoint'];
            $model->general->siteId = $old['id'];
            $model->general->secret = $old['secret'];
            $model->general->logLevel = $old['logLevel'] ?? 'INFO';
            $model->general->mtu = (string)($old['mtu'] ?? 1280);
            $model->general->port = isset($old['port']) ? (string)$old['port'] : '';
            $model->general->dns = $old['dns'] ?? '';
            $rc = is_file('/etc/rc.conf.d/newt') ? file_get_contents('/etc/rc.conf.d/newt') : '';
            $model->general->enabled = preg_match('/newt_enable=["\x27]?YES/i', $rc) ? '1' : '0';
            foreach ($model->performValidation() as $message) {
                // Never print field contents: they may contain site credentials.
                throw new RuntimeException('Imported settings failed validation: ' . $message->getField());
            }
        }
    }
    $model->serializeToConfig();
    $config->save('Install Pangolin Newt plugin and import existing site settings');
    echo "Newt plugin configuration initialized.\n";
} finally {
    $config->unlock();
}
