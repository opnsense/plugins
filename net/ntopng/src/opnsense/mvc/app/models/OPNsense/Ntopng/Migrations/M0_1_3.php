<?php
namespace OPNsense\Ntopng\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M0_1_3 extends BaseModelMigration
{
    public function run($model)
    {
        $config = Config::getInstance()->object();
        $ntopngConfig = $config->OPNsense->ntopng->general ?? null;

        if ($ntopngConfig) {
            $httpPort = (string)($ntopngConfig->httpport ?? '');
            if ($httpPort !== '') {
                $model->addresseshttp = "[::]:{$httpPort},0.0.0.0:{$httpPort}";
            }

            $httpsPort = (string)($ntopngConfig->httpsport ?? '');
            if ($httpsPort !== '') {
                $model->addresseshttps = "0.0.0.0:{$httpsPort}";
            }
        }

        parent::run($model);
    }
}
