<?php

namespace OPNsense\TelegramNotify\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'telegramnotify';
    protected static $internalModelClass = 'OPNsense\\TelegramNotify\\TelegramNotify';
}
