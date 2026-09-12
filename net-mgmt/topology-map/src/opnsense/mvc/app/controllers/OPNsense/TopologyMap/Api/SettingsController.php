<?php

namespace OPNsense\TopologyMap\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'topologymap';
    protected static $internalModelClass = 'OPNsense\\TopologyMap\\TopologyMap';
}
