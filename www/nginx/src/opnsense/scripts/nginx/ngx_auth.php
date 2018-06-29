<?php
$uri = $_SERVER['Original-URI'];
$host = $_SERVER['Original-HOST'];
$method = $_SERVER['Original-METHOD'];
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
$server_uuid = $_SERVER['SERVER-UUID'];

if (stristr($uri, 'pass')) {
    header("HTTP/1.1 200 OK");
} else {
    header("HTTP/1.1 401 Authorization Required");
}
file_put_contents('/tmp/var_server', json_encode($_SERVER));
