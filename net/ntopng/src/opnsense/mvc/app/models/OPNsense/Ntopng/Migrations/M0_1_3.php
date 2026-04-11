<?php
namespace OPNsense\Ntopng\Migrations;

use OPNsense\Base\BaseModelMigration;

class M0_1_3 extends BaseModelMigration
{
    public function run($model)
    {
        $httpPort = (string)$model->httpport;
        if ($httpPort !== '') {
            $model->addresseshttp = "[::]:{$httpPort},0.0.0.0:{$httpPort}";
        }

        $httpsPort = (string)$model->httpsport;
        if ($httpsPort !== '') {
            $model->addresseshttps = "[::]:{$httpsPort}";
        }

        parent::run($model);
    }
}
