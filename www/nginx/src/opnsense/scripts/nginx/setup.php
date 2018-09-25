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

require_once('config.inc');
require_once('certs.inc');
use \OPNsense\Nginx\Nginx;

function export_pem_file($filename, $data, $post_append = null) {
  $pem_content = trim(str_replace("\n\n", "\n", str_replace(
    "\r",
    "",
    base64_decode((string)$data)) . ($post_append == null ? '' : "\n" . $post_append)
  ));
  file_put_contents($filename, $pem_content);
  chmod($filename, 0600);
}

function find_cert($refid) {
  global $config;
  foreach($config['cert'] as $cert_entry) {
    if ($cert_entry['refid'] == $refid) {
      return $cert_entry;
    }
  }
}

function find_ca($refid) {
  global $config;
  foreach($config['ca'] as $cert_entry) {
    if ($cert_entry['refid'] == $refid) {
      return $cert_entry;
    }
  }
}

// export server certificates
if (!isset($config['OPNsense']['Nginx'])) {
  die("nginx is not configured");
}
$nginx = $config['OPNsense']['Nginx'];
if (!isset($nginx['http_server'])) {
  die("no http servers configured");
}
if (is_array($nginx['http_server']) && !isset($nginx['http_server']['servername'])) {
  $http_servers = $nginx['http_server'];
} else {
  $http_servers = array($nginx['http_server']);
}
@mkdir('/usr/local/etc/nginx/key', 0750, true);
@mkdir("/var/db/nginx/auth", 0750, true);
foreach ($http_servers as $http_server) {
  if (!empty($http_server['listen_https_port']) && !empty($http_server['certificate']))
  {
    // try to find the reference
    $cert = find_cert($http_server['certificate']);
    if (!isset($cert)) {
      next;
    }
    $chain = [];
    foreach (ca_chain_array($cert) as $entry) {
        $chain[] = base64_decode($entry['crt']);
    }
    $hostname = explode(',', $http_server['servername'])[0];
    export_pem_file(
      '/usr/local/etc/nginx/key/' . $hostname . '.pem',
      $cert['crt'],
      implode("\n", $chain)
    );
    export_pem_file(
      '/usr/local/etc/nginx/key/' . $hostname . '.key',
      $cert['prv']
    );
    if (!empty($http_server['ca'])) {
      foreach ($http_server['ca'] as $caref) {
        $ca = find_ca($caref);
        if (isset($ca)) {
          export_pem_file(
            '/usr/local/etc/nginx/key/' . $hostname . '_ca.pem',
            $ca['crt']
          );
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
        if (!empty($upstream['tls_enable']) && $upstream['tls_enable'] == '1')
        {
            // try to find the reference
            if (!empty($upstream['tls_client_certificate'])) {
                $cert = find_cert($upstream['tls_client_certificate']);
                if (isset($cert)) {
                    $chain = [];
                    foreach (ca_chain_array($cert) as $entry) {
                        $chain[] = base64_decode($entry['crt']);
                    }
                    $hostname = explode(',', $http_server['servername'])[0];
                    export_pem_file(
                        '/usr/local/etc/nginx/key/' . $upstream['tls_client_certificate'] . '.pem',
                        $cert['crt'],
                        implode("\n", $chain)
                    );
                    export_pem_file(
                        '/usr/local/etc/nginx/key/' . $upstream['tls_client_certificate'] . '.key',
                        $cert['prv']
                    );
                }
            }
            if (!empty($upstream['tls_trusted_certificate'])) {
                $cas = array();
                foreach ($http_server['ca'] as $caref) {
                    $ca = find_ca($caref);
                    if (isset($ca)) {
                        $cas[] = $ca;
                    }
                }
                export_pem_file(
                    '/usr/local/etc/nginx/key/trust_upstream_' . $upstream_uuid . '.pem',
                    implode("\n", $cas)
                );
            }
        }
    }
}
// end export client and upstream trust certificates

// export users
$nginx = new Nginx();
foreach ($nginx->userlist->__items as $user_list) {
    $attributes = $user_list->getAttributes();
    $uuid = $attributes['uuid'];
    $file = null;
    try {
        $file = fopen("/var/db/nginx/auth/" . $uuid, "wb");
        $users = explode(',',(string)$user_list->users);
        foreach ($users as $user) {
            $user_node = $nginx->getNodeByReference("credential." . $user);
            $username = (string)$user_node->username;
            $password = crypt((string)$user_node->password);
            fwrite($file, $username . ':' . $password . "\n");
        }
    }
    finally {
        if (isset($file)) {
            fclose($file);
        }
        unset($file);
    }
}
// create directories for cache
foreach ($nginx->cache_path->__items as $cache_path) {
    @mkdir((string)$cache_path->path,0755,true);
}
