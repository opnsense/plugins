<?php
namespace OPNsense\Ntopng\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M0_1_3 extends BaseModelMigration
{
    private function log($msg)
    {
        $logMsg = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
        @file_put_contents('/tmp/ntopng_migration_debug.log', $logMsg, FILE_APPEND | LOCK_EX);
    }

    public function run($model)
    {
        $this->log('--- Starting Migration M0_1_3 ---');
        
        $config = Config::getInstance()->object();
        $ntopngConfig = $config->OPNsense->ntopng->general ?? null;

        if ($ntopngConfig) {
            $httpPort = (string)($ntopngConfig->httpport ?? '');
            $this->log("Raw Config HTTP Port: '$httpPort'");
            if ($httpPort !== '') {
                $model->addresseshttp = "[::]:{$httpPort},0.0.0.0:{$httpPort}";
                $this->log("Migrated addresseshttp: '{$model->addresseshttp}'");
            }

            $httpsPort = (string)($ntopngConfig->httpsport ?? '');
            $this->log("Raw Config HTTPS Port: '$httpsPort'");
            if ($httpsPort !== '') {
                $model->addresseshttps = "[::]:{$httpsPort}";
                $this->log("Migrated addresseshttps: '{$model->addresseshttps}'");
            }
        } else {
            $this->log('No raw ntopng general config found');
        }

        parent::run($model);
        $this->log('--- Finished Migration M0_1_3 ---');
    }
}
