#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018 Fabian Franz
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

const KEY_DIRECTORY = '/usr/local/etc/nginx/key/';
const GROUP_OWNER = 'staff';
require_once('config.inc');
require_once('certs.inc');
use OPNsense\Nginx\Nginx;

function export_pem_file($filename, $data, $post_append = null)
{
    $pem_content = trim(str_replace("\n\n", "\n", str_replace(
        "\r",
        "",
        base64_decode((string)$data)
    ) . ($post_append == null ? '' : "\n" . $post_append)));
    file_put_contents($filename, $pem_content);
    chmod($filename, 0600);
}

function find_cert($refid)
{
    global $config;
    foreach ($config['cert'] as $cert_entry) {
        if ($cert_entry['refid'] == $refid) {
            return $cert_entry;
        }
    }
}

function find_ca($refid)
{
    global $config;
    foreach ($config['ca'] as $cert_entry) {
        if ($cert_entry['refid'] == $refid) {
            return $cert_entry;
        }
    }
}

// export server certificates
if (!isset($config['OPNsense']['Nginx'])) {
    die("nginx is not configured");
}
@mkdir('/usr/local/etc/nginx/key', 0750, true);
@mkdir("/var/db/nginx/auth", 0750, true);
@mkdir("/var/log/nginx", 0750, true);
@chgrp('/var/db/nginx', GROUP_OWNER);
@chgrp('/var/db/nginx/auth', GROUP_OWNER);
@chgrp('/var/log/nginx', GROUP_OWNER);
$nginx = $config['OPNsense']['Nginx'];
if (isset($nginx['http_server'])) {
    if (is_array($nginx['http_server']) && !isset($nginx['http_server']['servername'])) {
        $http_servers = $nginx['http_server'];
    } else {
        $http_servers = array($nginx['http_server']);
    }
    foreach ($http_servers as $http_server) {
        if (!empty($http_server['listen_https_address']) && !empty($http_server['certificate'])) {
          // try to find the reference
            $cert = find_cert($http_server['certificate']);
            if (!isset($cert)) {
                continue;
            }
            $chain = [];
            $ca_chain = ca_chain_array($cert);
            if (is_array($ca_chain)) {
                foreach ($ca_chain as $entry) {
                    $chain[] = base64_decode($entry['crt']);
                }
            }
            $hostname = explode(',', $http_server['servername'])[0];
            export_pem_file(
                KEY_DIRECTORY . $hostname . '.pem',
                $cert['crt'],
                implode("\n", $chain)
            );
            export_pem_file(
                KEY_DIRECTORY . $hostname . '.key',
                $cert['prv']
            );
            if (!empty($http_server['ca'])) {
                foreach ($http_server['ca'] as $caref) {
                    $ca = find_ca($caref);
                    if (isset($ca)) {
                        export_pem_file(
                            KEY_DIRECTORY . $hostname . '_ca.pem',
                            $ca['crt']
                        );
                    }
                }
            }
        }
    }
}
// end http, begin streams
if (isset($nginx['stream_server'])) {
    if (is_array($nginx['stream_server']) && !isset($nginx['stream_server']['@attributes']['uuid'])) {
        $stream_servers = $nginx['stream_server'];
    } else {
        $stream_servers = array($nginx['stream_server']);
    }
    foreach ($stream_servers as $stream_server) {
        if (!empty($stream_server['listen_address']) && !empty($stream_server['certificate'])) {
          // try to find the reference
            $cert = find_cert($stream_server['certificate']);
            if (!isset($cert)) {
                continue;
            }
            $chain = [];
            $ca_chain = ca_chain_array($cert);
            if (is_array($ca_chain)) {
                foreach ($ca_chain as $entry) {
                    $chain[] = base64_decode($entry['crt']);
                }
            }
            export_pem_file(
                KEY_DIRECTORY . $stream_server['@attributes']['uuid'] . '.pem',
                $cert['crt'],
                implode("\n", $chain)
            );
            export_pem_file(
                KEY_DIRECTORY . $stream_server['@attributes']['uuid'] . '.key',
                $cert['prv']
            );
            if (!empty($stream_server['ca'])) {
                foreach ($stream_server['ca'] as $caref) {
                    $ca = find_ca($caref);
                    if (isset($ca)) {
                        export_pem_file(
                            KEY_DIRECTORY . $stream_server['@attributes']['uuid'] . '_ca.pem',
                            $ca['crt']
                        );
                    }
                }
            }
        }
    }
}
// end export server certificates

// begin export client and upstream trust certificates
if (isset($nginx['upstream'])) {
    if (is_array($nginx['upstream']) && !isset($nginx['upstream']['description'])) {
        $upstreams = $nginx['upstream'];
    } else {
        $upstreams = array($nginx['upstream']);
    }

    foreach ($upstreams as $upstream) {
        $upstream_uuid = $upstream['@attributes']['uuid'];
        if (!empty($upstream['tls_enable']) && $upstream['tls_enable'] == '1') {
            // try to find the reference
            if (!empty($upstream['tls_client_certificate'])) {
                $cert = find_cert($upstream['tls_client_certificate']);
                if (isset($cert)) {
                    $chain = [];
                    foreach (ca_chain_array($cert) as $entry) {
                        $chain[] = base64_decode($entry['crt']);
                    }

                    export_pem_file(
                        KEY_DIRECTORY . $upstream['tls_client_certificate'] . '.pem',
                        $cert['crt'],
                        implode("\n", $chain)
                    );
                    export_pem_file(
                        KEY_DIRECTORY . $upstream['tls_client_certificate'] . '.key',
                        $cert['prv']
                    );
                }
            }
            if (!empty($upstream['tls_trusted_certificate'])) {
                $cas = array();
                $carefs = explode(",", $upstream['tls_trusted_certificate']);
                foreach ($carefs as $caref) {
                    $ca = find_ca($caref);
                    if (isset($ca)) {
                        $cas[] = base64_decode($ca['crt']);
                    }
                }
                export_pem_file(
                    '/usr/local/etc/nginx/key/trust_upstream_' . $upstream_uuid . '.pem',
                    '',
                    implode("\n", $cas)
                );
            }
        }
    }
}
// end export client and upstream trust certificates

// export users
$nginx = new Nginx();
foreach ($nginx->userlist->iterateItems() as $user_list) {
    $attributes = $user_list->getAttributes();
    $uuid = $attributes['uuid'];
    $file = null;
    try {
        $file = fopen("/var/db/nginx/auth/" . $uuid, "wb");
        $users = explode(',', (string)$user_list->users);
        foreach ($users as $user) {
            $user_node = $nginx->getNodeByReference("credential." . $user);
            $username = (string)$user_node->username;
            $password = password_hash((string)$user_node->password, PASSWORD_DEFAULT);
            fwrite($file, $username . ':' . $password . "\n");
        }
    } finally {
        if (isset($file)) {
            fclose($file);
            @chgrp('/var/db/nginx/auth/' . $uuid, GROUP_OWNER);
        }
        unset($file);
    }
}

// create directories for cache
foreach ($nginx->cache_path->iterateItems() as $cache_path) {
    @mkdir((string)$cache_path->path, 0755, true);
}

// create custom error pages
const ERRORPAGE_DIR = '/usr/local/etc/nginx/views';
@mkdir(ERRORPAGE_DIR, 0755, true);
$used_errorpages = array();
// search used error pages in http servers and locations
foreach (array($nginx->http_server, $nginx->location) as $entity) {
    foreach ($entity->iterateItems() as $element) {
        $pages = explode(',', $element->errorpages);
        foreach ($pages as $page) {
            $page = str_replace('-', '', $page);
            if (!in_array($page, $used_errorpages)) {
                $used_errorpages[] = $page;
            }
        }
    }
}
// search used WAF error pages
foreach ($nginx->location->iterateItems() as $location) {
    if ($location->secrules_errorpage != '') {
        $page = str_replace('-', '', $location->secrules_errorpage);
        if (!in_array($page, $used_errorpages)) {
            $used_errorpages[] = $page;
        }
    }
}
// create/update error pages
foreach ($nginx->errorpage->iterateItems() as $errorpage) {
    $uuid = str_replace('-', '', $errorpage->getAttributes()['uuid']);
    if (in_array($uuid, $used_errorpages)) {
        $filename = "error_$uuid.html";
        $content = base64_decode((string)$errorpage->pagecontent);
        // Does error page have a content?
        if (strlen($content) > 0) {
            $fs_hash = @hash_file("sha1", ERRORPAGE_DIR . "/$filename");
            if ($fs_hash !== hash("sha1", $content)) {
                @file_put_contents(ERRORPAGE_DIR . "/$filename", $content);
            }
            chmod(ERRORPAGE_DIR . "/$filename", 0644);
        } else {
            unset($used_errorpages[array_search($uuid, $used_errorpages)]);
        }
    }
}
// delete unused (old) error pages
$dir = new \DirectoryIterator(ERRORPAGE_DIR);
foreach ($dir as $file) {
    if ($file->isFile() && strpos($file->getFilename(), 'error_') === 0) {
        if (!in_array(substr($file->getFilename(), 6, 32), $used_errorpages)) {
            @unlink($file->getPathname());
        }
    }
}

// export TLS fingerprint database for MitM detection
$tls_fingerprint_database = array();
foreach ($nginx->tls_fingerprint->iterateItems() as $tls_fingerprint) {
    if ((string)$tls_fingerprint->trusted == '1') {
        $ciphers = explode(':', (string)$tls_fingerprint->ciphers);
        if (!empty((string)$tls_fingerprint->curves)) {
            $curves = explode(':', (string)$tls_fingerprint->curves);
        } else {
            $curves = array();
        }
        $tls_fingerprint_database[(string)$tls_fingerprint->user_agent] =
            array('ciphers' => $ciphers, 'curves' => $curves);
    }
}

file_put_contents(
    '/usr/local/etc/nginx/tls_fingerprints.json',
    empty($tls_fingerprint_database) ? '{}' :  json_encode($tls_fingerprint_database)
);
chmod('/usr/local/etc/nginx/tls_fingerprints.json', 0644);

passthru('/usr/local/etc/rc.d/php-fpm start');
