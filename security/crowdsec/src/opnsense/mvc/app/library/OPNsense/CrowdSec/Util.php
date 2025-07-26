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
}
