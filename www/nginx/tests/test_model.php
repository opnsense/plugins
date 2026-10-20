<?php

// SPDX-License-Identifier: BSD-2-Clause
// Use OPNsense's actual model/validators with a temporary configuration.

use OPNsense\Autoload\Loader;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Nginx\Nginx;

$mvc = getenv('OPNSENSE_MVC') ?: '/usr/local/opnsense/mvc';
require_once $mvc . '/app/config/AppConfig.php';
require_once $mvc . '/app/library/OPNsense/Autoload/Loader.php';
(new Loader([
    __DIR__ . '/../src/opnsense/mvc/app/models',
    $mvc . '/app/models',
    $mvc . '/app/library'
]))->register();
require_once __DIR__ . '/../src/opnsense/mvc/app/models/OPNsense/Nginx/Nginx.php';

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/nginx-model-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
new AppConfig(['application' => ['configDir' => $directory, 'tempDir' => $directory]]);
file_put_contents(
    $directory . '/config.xml',
    '<opnsense><system/><interfaces/><OPNsense><Nginx version="1.35.2">' .
    '<ban uuid="b40faafe-fb51-49a8-a1de-f7d3fdc47316">' .
    '<ip>192.0.2.1</ip><time>1</time></ban></Nginx></OPNsense></opnsense>'
);

try {
    $model = new Nginx();
    check((string)$model->http->bot_protection_trusted_networks === '', 'Missing setting must be empty');
    $model->runMigrations();
    check((string)$model->http->bot_protection_trusted_networks === '', 'Migration must not trust any networks');
    $valid = [
        '', '192.0.2.5', '2001:db8::5', '192.0.2.0/24', '2001:db8::/64',
        '192.0.2.1/32', '2001:db8::1/128', '0.0.0.0/0', '::/0',
        '192.0.2.0/24,198.51.100.0/24,192.0.2.5,2001:db8::/64,2001:db8:1::5'
    ];
    foreach ($valid as $value) {
        $model->setNodes(['http' => ['bot_protection_trusted_networks' => $value]]);
        check(count($model->performValidation()) === 0, 'Valid input rejected: ' . $value);
        $model->serializeToConfig();
        Config::getInstance()->save(['description' => 'nginx model test'], false);
        Config::getInstance()->forceReload();
        $model = new Nginx();
        check((string)$model->http->bot_protection_trusted_networks === $value, 'XML round trip failed');
        if ($value !== '') {
            $data = $model->getNodes()['http']['bot_protection_trusted_networks'];
            check(array_keys($data) === explode(',', $value), 'GUI/API list values changed');
            foreach ($data as $item) {
                check($item['selected'] == 1, 'GUI/API list item is not selected');
            }
        }
        check(count(iterator_to_array($model->ban->iterateItems())) === 1, 'Existing bans must be retained');
    }
    foreach ([
        '999.1.2.3', '192.0.2.0/33', '192.0.2.0/-1', '192.0.2.0/abc',
        '192.0.2.0/24/1', '2001:db8::/129', '2001:db8::/-1', '2001:db8:::1',
        'example.com', 'any', '192.0.2.0/24,invalid', '192.0.2.0/24,',
        '192.0.2.0/24;default 1'
    ] as $value) {
        $model->setNodes(['http' => ['bot_protection_trusted_networks' => $value]]);
        check(count($model->performValidation()) > 0, 'Invalid input accepted: ' . $value);
    }
    $model->setNodes(['http' => ['bot_protection_trusted_networks' => '']]);
    $model->serializeToConfig();
    Config::getInstance()->save(['description' => 'nginx model test'], false);
    Config::getInstance()->forceReload();
    check((string)(new Nginx())->http->bot_protection_trusted_networks === '', 'Clearing the list failed');
    echo "Model validation, GUI/API list data, XML persistence and ban preservation passed.\n";
} finally {
    foreach (glob($directory . '/*') as $filename) {
        unlink($filename);
    }
    rmdir($directory);
}
