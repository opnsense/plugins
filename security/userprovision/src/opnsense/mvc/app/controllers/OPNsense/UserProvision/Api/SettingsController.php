<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'Settings';
    protected static $internalModelClass = 'OPNsense\\UserProvision\\Settings';
    // expose standard get/set endpoints under /api/userprovision/settings/get and /set
    public function getAction(): array
    {
        return $this->getBase('settings');
    }
    public function setAction(): array
    {
        return $this->setBase('settings');
    }
}


