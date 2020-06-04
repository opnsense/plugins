<?php

namespace OPNsense\RadSecProxy\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;

class ClientsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'radsecproxy';
    protected static $internalModelClass = 'OPNsense\RadSecProxy\RadSecProxy';

    public function searchItemAction()
    {
        return $this->searchBase("clients.client", array('enabled', 'description', 'host', 'identifier', 'type'), "name");
    }

    public function setItemAction($uuid)
    {
        return $this->setBase("client", "clients.client", $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase("client", "clients.client");
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("client", "clients.client", $uuid);
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("clients.client", $uuid);
    }

    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase("clients.client", $uuid, $enabled);
    }
}