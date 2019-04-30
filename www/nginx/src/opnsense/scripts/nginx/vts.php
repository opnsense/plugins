#!/usr/local/bin/php
<?php

$ch = curl_init();
curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/var/run/nginx_status.sock');
curl_setopt($ch, CURLOPT_URL, "http://localhost/vts");
curl_exec($ch);
