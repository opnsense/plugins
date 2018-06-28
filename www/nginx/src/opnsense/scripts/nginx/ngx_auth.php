<?php
$uri = $_SERVER['Original-URI'];
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';

if (stristr($uri, 'pass')) {
    header("HTTP/1.1 200 OK");
} else {
    header("HTTP/1.1 401 Authorization Required");
}
file_put_contents('/tmp/var_server', json_encode($_SERVER));
