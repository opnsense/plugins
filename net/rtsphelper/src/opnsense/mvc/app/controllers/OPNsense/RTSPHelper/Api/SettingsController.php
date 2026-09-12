<?php

namespace OPNsense\RTSPHelper\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\RTSPHelper\General';
    protected static $internalModelName = 'rtsphelper';
}
