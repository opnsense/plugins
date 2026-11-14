<?php

namespace OPNsense\Yggdrasil\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Backend;

class M1_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

        if ((string)$model->general->PrivateKey !== '') {
            return;
        }

        $output = (new Backend())->configdRun('yggdrasil genconf');
        $generated = json_decode($output, true);
        $key = $generated['PrivateKey'] ?? null;

        if (!is_string($key) || !preg_match('/\A[a-fA-F0-9]{128}\z/', $key)) {
            throw new \RuntimeException('Yggdrasil did not return a valid private key.');
        }

        $model->general->PrivateKey = $key;
    }
}
