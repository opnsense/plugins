<?php

namespace OPNsense\Freeradius;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Backend;

class Proxy extends BaseModel
{
    /**
     * check if module is enabled
     * @return bool is the ZabbixAgent service enabled
     */
    public function isEnabled()
    {
        if ((string)$this->settings->main->enabled === "1") {
            return true;
        }
        return false;
    }
}
