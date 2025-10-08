#!/usr/local/bin/php
<?php
error_reporting(0);
header('Content-Type: application/json');

$paths = ['/conf/config.xml','/usr/local/etc/config.xml','/etc/config.xml','/var/etc/config.xml','./config.xml','../config.xml','../../config.xml'];
$xml = null; $used = null;
foreach ($paths as $p) {
    if (is_readable($p)) { $xml = @simplexml_load_file($p); $used = $p; if ($xml) break; }
}
$rows = [];
if ($xml && isset($xml->system) && isset($xml->system->authserver)) {
    foreach ($xml->system->authserver as $srv) {
        if ((string)($srv->type ?? '') !== 'radius') { continue; }
        $name = (string)($srv->name ?? '');
        $host = (string)($srv->host ?? '');
        $auth_port = (string)($srv->auth_port ?? $srv->radius_auth_port ?? '1812');
        $acct_port = (string)($srv->acct_port ?? $srv->radius_acct_port ?? '');
        $timeout = (string)($srv->timeout ?? $srv->radius_timeout ?? '5');
        $stationid = (string)($srv->radius_stationid ?? '');
        $descr = (string)($srv->descr ?? '');
        $services = (!empty($auth_port) && !empty($acct_port)) ? 'both' : 'auth';
        $rows[] = [
            'name' => $name,
            'host' => $host,
            'secret' => '***',
            'services' => $services,
            'auth_port' => $auth_port,
            'acct_port' => $acct_port === '' ? null : $acct_port,
            'timeout' => $timeout,
            'stationid' => $stationid,
            'descr' => $descr,
            'refid' => (string)($srv->refid ?? ''),
        ];
    }
}
echo json_encode(['rows'=>$rows,'total'=>count($rows),'config_path'=>$used]);
exit(0);
?>


