<?php
namespace OPNsense\Ntopng\General\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M0_1_3 extends BaseModelMigration
{
    function ntop_dbg($msg) {
        // 1) append to a known temp file so we can read it reliably
        @file_put_contents('/tmp/ntopng_migration.log', date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND|LOCK_EX);
        // 2) also write to STDERR so it appears when you run the migration interactively
        if (defined('STDERR')) {
            @fwrite(STDERR, date('c') . ' ' . $msg . PHP_EOL);
        }
    }
    public function run($model)
    {


        parent::run($model);
        $general = $model->getNodeByReference('general');
        $general->addresseshttp  = "[::]:5555,0.0.0.0:5555";
        $general->addresseshttps = "[::]:5555";


        // $config = Config::getInstance()->object();
        // // $general = $model->getNodeByReference('general');
        // $general = $model;
        //
        // $httpPort = (string)$config->OPNsense->ntopng->general->httpport;
        // if (true) {
        //     $general->addresseshttp = "[::]:{$httpPort},0.0.0.0:{$httpPort}";
        // }
        //
        // $httpsPort = (string)$config->OPNsense->ntopng->general->httpsport;
        // if (true) {
        //     $general->addresseshttps = "[::]:{$httpsPort}";
        // }
        // if ($general !== null) {
        //
        // }

    }
}
