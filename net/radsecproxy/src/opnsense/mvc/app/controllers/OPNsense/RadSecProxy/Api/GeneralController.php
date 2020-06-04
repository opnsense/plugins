<?php

namespace OPNsense\RadSecProxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'radsecproxy';
    protected static $internalModelClass = 'OPNsense\RadSecProxy\RadSecProxy';
}
