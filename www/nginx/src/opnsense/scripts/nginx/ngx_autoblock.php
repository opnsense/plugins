#!/usr/local/bin/php
<?php

require_once('config.inc');

use OPNsense\Firewall\Alias;
use OPNsense\Nginx\AccessLogParser;
use OPNsense\Core\Config;

$permanent_ban_file = '/var/log/nginx/permanentban.access.log';
$autoblock_alias_name = 'nginx_autoblock';

if (!file_exists($permanent_ban_file)) {
    echo "No Log exists - nothing to do";
    exit(0);
}

$log_parser = new AccessLogParser($permanent_ban_file);

$log_lines = $log_parser->get_result();

$model = new Alias();

$blacklist_element = null;
foreach ($model->aliases->alias->__items as $alias) {
    if ((string)$alias->name == $autoblock_alias_name) {
        if ((string)$alias->type != 'host') {
            echo "alias is misconfigured - exiting";
            exit(0);
        } else {
            $blacklist_element = $alias;
            break;
        }
    }
}

// does not exist yet, create it
if ($blacklist_element == null) {
    $blacklist_element = $model->aliases->alias->Add();
    $blacklist_element->name = $autoblock_alias_name;
    $blacklist_element->type = "host";
}

$alias_ips = explode("\n", $blacklist_element->content);
$new_ips = array_map( function ($row) { return $row->remote_ip; }, $log_lines);
$result = array_filter(array_unique(array_merge($alias_ips, $new_ips)));

$blacklist_element->content = implode("\n", $result);
$val_result = $model->performValidation(false);
if (count($val_result) == 0) {
    $model->serializeToConfig();
    Config::getInstance()->save();
    echo "saved"; exit(0);
}


