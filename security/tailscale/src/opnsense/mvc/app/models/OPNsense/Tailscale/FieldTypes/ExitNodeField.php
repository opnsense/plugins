<?php

namespace OPNsense\Tailscale\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Backend;

class ExitNodeField extends BaseListField
{
    private static array $internalCacheOptionList = [];
    protected $internalIsContainer = false;

    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalCacheOptionList)) {
            $response = json_decode(trim((new Backend())->configdRun('tailscale tailscale-status')), true);
            $exitNodes = [];
            $exitNodes[''] = 'None';
            foreach ($response['Peer'] as $peer) {
                if ($peer['ExitNodeOption']) {
                    $exitNodes[$peer['HostName']] = $peer['HostName'];
                }
            }

            self::$internalCacheOptionList = $exitNodes;
        }
        $this->internalOptionList = self::$internalCacheOptionList;
    }
}

# vim:ts=4 sw=4 et:
