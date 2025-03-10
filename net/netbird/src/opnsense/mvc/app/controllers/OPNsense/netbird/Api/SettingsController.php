<?php


namespace OPNsense\netbird\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * netbird settings controller
 * @package OPNsense\netbird
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'netbird';
    protected static $internalModelClass = 'OPNsense\netbird\Netbird';
}
