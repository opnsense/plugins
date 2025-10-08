<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;

class LogsController extends ApiControllerBase
{
    public function dhcpAction(): array
    {
        $user = trim((string)($this->request->get('user') ?? ''));
        $limit = (int)($this->request->get('limit') ?? 200);
        $q = trim((string)($this->request->get('q') ?? ''));
        $since = (string)($this->request->get('since') ?? '');
        $until = (string)($this->request->get('until') ?? '');
        $format = strtolower(trim((string)($this->request->get('format') ?? 'json')));
        // naive grep of dhcp logs mapping descr/host to user; adapt as needed
        $out = [];
        $dhcpPath = '/var/log/dhcpd.log';
        if (class_exists('OPNsense\\UserProvision\\Settings')) {
            $mdl = new \OPNsense\UserProvision\Settings();
            $dhcpPath = (string)$mdl->settings->dhcpLogPath ?? $dhcpPath;
        }
        @exec("/usr/bin/tail -n ".(int)$limit." " . escapeshellarg($dhcpPath) . " 2>/dev/null", $out);
        $rows = [];
        foreach ($out as $line) {
            if ($user!=='' && stripos($line, $user) === false) { continue; }
            if ($q!=='' && stripos($line, $q) === false) { continue; }
            if ($since!=='' && strtotime($line) && strtotime($line) < strtotime($since)) { continue; }
            if ($until!=='' && strtotime($line) && strtotime($line) > strtotime($until)) { continue; }
            $rows[] = $line;
        }
        if ($format === 'csv') { return ['rows'=>array_map(function($l){ return [$l]; }, $rows)]; }
        return ['rows'=>$rows];
    }

    public function accessAction(): array
    {
        $user = trim((string)($this->request->get('user') ?? ''));
        $limit = (int)($this->request->get('limit') ?? 200);
        $q = trim((string)($this->request->get('q') ?? ''));
        $since = (string)($this->request->get('since') ?? '');
        $until = (string)($this->request->get('until') ?? '');
        $format = strtolower(trim((string)($this->request->get('format') ?? 'json')));
        // if using captive portal or radius accounting logs locally; fallback to system.log
        $out = [];
        $accessPath = '/var/log/system.log';
        if (class_exists('OPNsense\\UserProvision\\Settings')) {
            $mdl = new \OPNsense\UserProvision\Settings();
            $accessPath = (string)$mdl->settings->accessLogPath ?? $accessPath;
        }
        @exec("/usr/bin/tail -n ".(int)$limit." " . escapeshellarg($accessPath) . " 2>/dev/null", $out);
        $rows = [];
        foreach ($out as $line) {
            if ($user!=='' && stripos($line, $user) === false) { continue; }
            if ($q!=='' && stripos($line, $q) === false) { continue; }
            if ($since!=='' && strtotime($line) && strtotime($line) < strtotime($since)) { continue; }
            if ($until!=='' && strtotime($line) && strtotime($line) > strtotime($until)) { continue; }
            $rows[] = $line;
        }
        if ($format === 'csv') { return ['rows'=>array_map(function($l){ return [$l]; }, $rows)]; }
        return ['rows'=>$rows];
    }
}


