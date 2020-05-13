<?php

namespace OPNsense\RadSecProxy\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;

class RealmsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'radsecproxy';
    protected static $internalModelClass = 'OPNsense\RadSecProxy\RadSecProxy';

    public function searchItemAction()
    {
        return $this->searchBase("realms.realm", array('enabled', 'description', 'realm'), "description");
    }

    public function setItemAction($uuid)
    {
        return $this->setBase("realm", "realms.realm", $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase("realm", "realms.realm");
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("realm", "realms.realm", $uuid);
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("realms.realm", $uuid);
    }

    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase("realms.realm", $uuid, $enabled);
    }
}