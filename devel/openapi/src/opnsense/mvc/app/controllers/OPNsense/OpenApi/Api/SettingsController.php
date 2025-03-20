<?php

namespace OPNsense\OpenApi\Api;

use OPNsense\Core\Config;
use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class SettingsController Handles settings related API actions for the OpenApi module
 * @package OPNsense\OpenApi
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\OpenApi\OpenApi';
    protected static $internalModelName = 'openapi';
}
