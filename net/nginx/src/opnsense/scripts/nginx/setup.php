#!/usr/local/bin/php
<?php

require_once 'config.inc';
require_once("certs.inc");

function export_pem_file($filename, $data) {
  $pem_content = trim(str_replace("\n\n", "\n", str_replace(
    "\r",
    "",
    base64_decode((string)$data))
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

// export certificates
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
@mkdir('/usr/local/etc/nginx/key', 750, true);
foreach ($http_servers as $http_server) {
  if (!empty($http_server['listen_https_port']) && !empty($http_server['certificate']))
  {
    // try to find the reference
      $cert = find_cert($http_server['certificate']);
      if (!isset($cert)) {
        next;
      }
      $hostname = explode(',', $http_server['servername'])[0];
      export_pem_file(
        '/usr/local/etc/nginx/key/' . $hostname . '.pem',
        $cert['crt']
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
              $ca['prv']                                                            
            );
          }
        }
      }
  }
}


