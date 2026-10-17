<?php
/*
 * Copyright (c) 2026 Emanuele Baglini
 * SPDX-License-Identifier: BSD-2-Clause
 */
$server = stream_socket_server('unix://' . $argv[1], $errno, $errstr);
if (!$server) { throw new RuntimeException($errstr); }
echo "READY\n"; flush();
$connection = stream_socket_accept($server, 10);
if (!$connection) { throw new RuntimeException('test client did not connect'); }
$event = fread($connection, 4096);
fwrite($connection, "11111111-2222-4333-8444-555555555555\n\0\0\0");
fclose($connection);
echo 'EVENT:' . base64_encode($event) . "\n"; flush();
/* Deliberately keep the simulated action incomplete until the test releases it. */
fgets(STDIN);
fclose($server);
unlink($argv[1]);
