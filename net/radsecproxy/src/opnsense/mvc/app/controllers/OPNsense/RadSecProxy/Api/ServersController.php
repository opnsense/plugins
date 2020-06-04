<?php

namespace OPNsense\RadSecProxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class ServersController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'radsecproxy';
    protected static $internalModelClass = 'OPNsense\RadSecProxy\RadSecProxy';

    public function searchItemAction()
    {
        return $this->searchBase(
            "servers.server",
            array('description', 'host', 'type', 'identifier', 'tlsConfig'),
            "name"
        );
    }

    public function setItemAction($uuid)
    {
        return $this->setBase("server", "servers.server", $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase("server", "servers.server");
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("server", "servers.server", $uuid);
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("servers.server", $uuid);
    }

    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase("servers.server", $uuid, $enabled);
    }
}
