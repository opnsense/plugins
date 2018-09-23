<?php

namespace OPNsense\Shadowsocks\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use SmartSoft\ProxyUserACL\Api\SettingsController;


class M1_0_1 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

        if (get_class($model) != 'OPNsense\\Shadowsocks\\Server') {
            return;
        }

        if (!isset(Config::getInstance()->object()->OPNsense->shadowsocks->general))
            return;

        foreach (Config::getInstance()->object()->OPNsense->shadowsocks->general->children() as $key => $child) {
            $model->{$key} = $child->__toString();
        }

        unset(Config::getInstance()->object()->OPNsense->shadowsocks->general);
        $model->serializeToConfig();
        Config::getInstance()->save();
    }
}
