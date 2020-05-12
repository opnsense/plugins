<?php

namespace OPNsense\RadSecProxy\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;

class TlsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'radsecproxy';
    protected static $internalModelClass = 'OPNsense\RadSecProxy\RadSecProxy';

    public function searchItemAction()
    {
        return $this->searchBase("tlsConfigs.tlsConfig", array('description', 'name'), "name");
    }

    public function setItemAction($uuid)
    {
        return $this->setBase("tlsConfig", "tlsConfigs.tlsConfig", $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase("tlsConfig", "tlsConfigs.tlsConfig");
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("tlsConfig", "tlsConfigs.tlsConfig", $uuid);
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("tlsConfigs.tlsConfig", $uuid);
    }

    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase("tlsConfigs.tlsConfig", $uuid, $enabled);
    }
}