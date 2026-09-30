<?php

namespace OPNsense\CrowdSec;

class Util
{
    public static function trimLocalPath($local_path): string
    {
        $prefix = '/usr/local/etc/crowdsec/';
        if (str_starts_with($local_path, $prefix)) {
            return substr($local_path, strlen($prefix));
        }
        return $local_path;
    }

    /**
     * message for a list that cscli could not retrieve
     * @return string
     */
    public static function noDataMessage(): string
    {
        if (!(new General())->lapi_enabled->isEqual('1')) {
            return gettext('No data: the local LAPI is disabled.');
        }
        return 'unable to retrieve data';
    }
}
