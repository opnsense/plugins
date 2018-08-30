<?php
namespace OPNsense\UnboundBL\Api;
use OPNsense\Base\ApiMutableModelControllerBase;
class SettingsController extends ApiMutableModelControllerBase
{
    static protected $internalModelClass = '\OPNsense\UnboundBL\Settings';
    static protected $internalModelName = 'settings';
}
