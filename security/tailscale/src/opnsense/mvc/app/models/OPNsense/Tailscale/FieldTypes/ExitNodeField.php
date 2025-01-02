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

            if (is_array($response) && array_key_exists('Peer', $response)) {
                foreach ($response['Peer'] as $peer) {
                    if ($peer['ExitNodeOption']) {
                        $exitNodes[$peer['TailscaleIPs'][0]] = $peer['HostName'];
                    }
                }
            }

            self::$internalCacheOptionList = $exitNodes;
        }
        $this->internalOptionList = self::$internalCacheOptionList;
    }
}
