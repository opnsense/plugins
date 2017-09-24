#!/usr/local/bin/php
<?php

require_once("config.inc");
require_once('tor_helper.php');
use \OPNsense\Tor\HiddenService;


$hostnames = array();
$services = new HiddenService();
foreach ($services->service->__items as $service) {
    $directory_name = ((string)$service->name);
    $hostdir = TOR_DATA_DIR . '/' . $directory_name;
    if (!file_exists($hostdir)) {
        mkdir($hostdir);
        chown($hostdir, '_tor');
        chgrp($hostdir, '_tor');
        chmod($hostdir, 0700);
    }
}
