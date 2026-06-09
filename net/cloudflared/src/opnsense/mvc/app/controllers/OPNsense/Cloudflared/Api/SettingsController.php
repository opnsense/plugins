<?php

namespace OPNsense\Cloudflared\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Cloudflared\Cloudflared;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'Cloudflared';
    protected static $internalModelClass = 'OPNsense\Cloudflared\Cloudflared';
}
