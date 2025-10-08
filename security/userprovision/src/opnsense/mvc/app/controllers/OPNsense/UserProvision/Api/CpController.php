<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class CpController extends ApiControllerBase
{
    use AuditTrait;
    use ValidationTrait;

    private function getCpNode()
    {
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->captiveportal)) { $cfg->addChild('captiveportal'); }
        return $cfg->captiveportal;
    }

    private function getOpnsenseCpNode()
    {
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->OPNsense)) { $cfg->addChild('OPNsense'); }
        if (!isset($cfg->OPNsense->captiveportal)) { $cfg->OPNsense->addChild('captiveportal'); }
        if (!isset($cfg->OPNsense->captiveportal->zones)) { $cfg->OPNsense->captiveportal->addChild('zones'); }
        return $cfg->OPNsense->captiveportal;
    }

    private function findZone($cp, string $zone)
    {
        if (!isset($cp->zone)) { return null; }
        foreach ($cp->zone as $z) {
            if ((string)($z->name ?? '') === $zone) { return $z; }
        }
        return null;
    }

    private function findOpnZone($opn, string $zone)
    {
        if (!isset($opn->zones) || !isset($opn->zones->zone)) { return null; }
        foreach ($opn->zones->zone as $z) {
            if ((string)($z->name ?? '') === $zone) { return $z; }
        }
        return null;
    }

    private function authServerExists(string $name): bool
    {
        $configArray = \OPNsense\Core\Config::getInstance()->toArray();
        $authServers = $configArray['system']['authserver'] ?? [];
        
        // Ensure it's an array (might be single element)
        if (!is_array($authServers)) {
            $authServers = [$authServers];
        }
        
        foreach ($authServers as $srv) {
            if (($srv['name'] ?? '') === $name && ($srv['type'] ?? '') === 'radius') { 
                return true; 
            }
        }
        return false;
    }

    public function zoneEnsureAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $name = trim((string)($p['name'] ?? ''));
        $description = trim((string)($p['description'] ?? ''));
        $ifaces = isset($p['interfaces']) ? (array)$p['interfaces'] : [];
        if ($name === '') { return ['status'=>'error','message'=>'missing name']; }
        $cp = $this->getCpNode();
        $opn = $this->getOpnsenseCpNode();
        $z = $this->findZone($cp, $name);
        $zo = $this->findOpnZone($opn, $name);
        if ($z === null) {
            $z = $cp->addChild('zone');
            $z->addChild('name', $name);
            if ($description !== '') { $z->addChild('descr', $description); }
            // assign numeric zoneid expected by UI if absent
            $maxId = 0;
            if (isset($cp->zone)) {
                foreach ($cp->zone as $zz) {
                    $maxId = max($maxId, (int)((string)($zz->zoneid ?? '0')));
                }
            }
            $z->addChild('zoneid', (string)($maxId + 1));
            if (!empty($ifaces)) {
                $seen = [];
                foreach ($ifaces as $ifn) {
                    $ifn = (string)$ifn;
                    if ($this->isValidIface($ifn) && !isset($seen[$ifn])) { $z->addChild('interface', $ifn); $seen[$ifn] = true; }
                }
            }

            // optional advanced fields on create (legacy)
            $enabledIn = (string)($p['enabled'] ?? '');
            if ($enabledIn !== '') { $z->addChild('enable', ((string)$enabledIn ? '1' : '0')); }
            if (!empty($p['zoneid']) && ctype_digit((string)$p['zoneid'])) { $z->zoneid = (string)$p['zoneid']; }
            if (!empty($p['hostname'])) { $z->addChild('hostname', (string)$p['hostname']); }
            if (!empty($p['template'])) { $z->addChild('template', (string)$p['template']); }
            if (!empty($p['idle_timeout'])) { $z->addChild('idle_timeout', (string)$p['idle_timeout']); }
            if (!empty($p['hard_timeout'])) { $z->addChild('hard_timeout', (string)$p['hard_timeout']); }
            if (!empty($p['concurrent_logins'])) { $z->addChild('concurrent_logins', (string)$p['concurrent_logins']); }
            if (isset($p['enforce_local_group'])) { $z->addChild('enforce_local_group', ((string)$p['enforce_local_group'] ? '1' : '0')); }
            // allowed lists (legacy)
            $allowedAddrs = $p['allowed_addresses'] ?? [];
            if (!is_array($allowedAddrs)) { $allowedAddrs = strlen((string)$allowedAddrs) ? [ (string)$allowedAddrs ] : []; }
            foreach ($allowedAddrs as $addr) { $addr = trim((string)$addr); if ($addr!=='') { $z->addChild('allowed_ip', $addr); } }
            $allowedMacs = $p['allowed_macs'] ?? [];
            if (!is_array($allowedMacs)) { $allowedMacs = strlen((string)$allowedMacs) ? [ (string)$allowedMacs ] : []; }
            foreach ($allowedMacs as $mac) { $mac = trim((string)$mac); if ($mac!=='') { $z->addChild('allowed_mac', $mac); } }

            // OPNsense tree (use description tag, not descr)
            if ($zo === null) {
                $zo = $opn->zones->addChild('zone');
                $zo->addChild('name', $name);
                if ($description !== '') { $zo->addChild('description', $description); $zo->addChild('descr', $description); }
                $zo->addChild('zoneid', (string)$z->zoneid);
                $zo->addChild('enabled', '0');
                $zo->addChild('authmethod', 'local');
                $zo->addChild('accounting', '0');
                $zo->addChild('accounting_interval', '');
                $ifsNode = $zo->addChild('interfaces');
                if (!empty($ifaces)) {
                    $seen2 = [];
                    foreach ($ifaces as $ifn) {
                        $ifn = (string)$ifn;
                        if ($this->isValidIface($ifn) && !isset($seen2[$ifn])) { $ifsNode->addChild('interface', $ifn); $seen2[$ifn] = true; }
                    }
                }
                if ($enabledIn !== '') { $zo->enabled = ((string)$enabledIn ? '1' : '0'); }
                if (!empty($p['zoneid']) && ctype_digit((string)$p['zoneid'])) { $zo->zoneid = (string)$p['zoneid']; }
                if (!empty($p['hostname'])) { $zo->addChild('hostname', (string)$p['hostname']); }
                if (!empty($p['template'])) { $zo->addChild('template', (string)$p['template']); }
                if (!empty($p['idle_timeout'])) { $zo->addChild('idle_timeout', (string)$p['idle_timeout']); $zo->addChild('idletimeout', (string)$p['idle_timeout']); }
                if (!empty($p['hard_timeout'])) { $zo->addChild('hard_timeout', (string)$p['hard_timeout']); $zo->addChild('hardtimeout', (string)$p['hard_timeout']); }
                if (!empty($p['concurrent_logins'])) { $zo->addChild('concurrent_logins', (string)$p['concurrent_logins']); $zo->addChild('concurrentlogins', (string)$p['concurrent_logins']); }
                if (isset($p['certificate'])) { $zo->addChild('certificate', (string)$p['certificate']); }
                if (isset($p['enforce_local_group'])) { $zo->addChild('enforce_local_group', ((string)$p['enforce_local_group'] ? '1' : '0')); }
                if (!empty($p['local_groups'])) {
                    $lg = is_array($p['local_groups']) ? $p['local_groups'] : [ (string)$p['local_groups'] ];
                    $lgNode = $zo->addChild('local_groups');
                    foreach ($lg as $g) { $g = trim((string)$g); if ($g!=='') { $lgNode->addChild('group', $g); } }
                }
                if (!empty($allowedAddrs)) {
                    $aaNode = $zo->addChild('allowed_addresses');
                    foreach ($allowedAddrs as $addr) { $addr = trim((string)$addr); if ($addr!=='') { $aaNode->addChild('address', $addr); } }
                }
                if (!empty($allowedMacs)) {
                    $amNode = $zo->addChild('allowed_macs');
                    foreach ($allowedMacs as $mac) { $mac = trim((string)$mac); if ($mac!=='') { $amNode->addChild('mac', $mac); } }
                }
            }
            Config::getInstance()->save();
            $this->audit('cp.zone.ensure', ['name'=>$name,'created'=>true]);
            return ['status'=>'ok','created'=>true];
        }
        // update
        if ($description !== '') { $z->descr = $description; }
        if (!isset($zo)) { $zo = $this->findOpnZone($opn, $name); }
        if ($zo && $description !== '') { $zo->description = $description; $zo->descr = $description; }
        if (!isset($z->zoneid) || (string)$z->zoneid === '') {
            $maxId = 0;
            if (isset($cp->zone)) {
                foreach ($cp->zone as $zz) {
                    $maxId = max($maxId, (int)((string)($zz->zoneid ?? '0')));
                }
            }
            $z->zoneid = (string)($maxId + 1);
            if ($zo) { $zo->zoneid = (string)$z->zoneid; }
        }
        if (!empty($ifaces)) {
            unset($z->interface);
            $seen = [];
            foreach ($ifaces as $ifn) {
                $ifn = (string)$ifn;
                if ($this->isValidIface($ifn) && !isset($seen[$ifn])) { $z->addChild('interface', $ifn); $seen[$ifn] = true; }
            }
            if ($zo) {
                if (isset($zo->interfaces)) { unset($zo->interfaces); }
                $ifsNode = $zo->addChild('interfaces');
                $seen2 = [];
                foreach ($ifaces as $ifn) {
                    $ifn = (string)$ifn;
                    if ($this->isValidIface($ifn) && !isset($seen2[$ifn])) { $ifsNode->addChild('interface', $ifn); $seen2[$ifn] = true; }
                }
            }
        }
        // advanced fields on update
        if (!isset($zo)) { $zo = $this->findOpnZone($opn, $name); }
        if (isset($p['enabled'])) { $z->enable = ((string)$p['enabled'] ? '1' : '0'); if ($zo) { $zo->enabled = $z->enable; } }
        if (!empty($p['hostname'])) { $z->hostname = (string)$p['hostname']; if ($zo) { $zo->hostname = (string)$p['hostname']; } }
        if (!empty($p['template'])) { $z->template = (string)$p['template']; if ($zo) { $zo->template = (string)$p['template']; } }
        if (!empty($p['idle_timeout'])) { $z->idle_timeout = (string)$p['idle_timeout']; if ($zo) { $zo->idle_timeout = (string)$p['idle_timeout']; $zo->idletimeout = (string)$p['idle_timeout']; } }
        if (!empty($p['hard_timeout'])) { $z->hard_timeout = (string)$p['hard_timeout']; if ($zo) { $zo->hard_timeout = (string)$p['hard_timeout']; $zo->hardtimeout = (string)$p['hard_timeout']; } }
        if (!empty($p['concurrent_logins'])) { $z->concurrent_logins = (string)$p['concurrent_logins']; if ($zo) { $zo->concurrent_logins = (string)$p['concurrent_logins']; $zo->concurrentlogins = (string)$p['concurrent_logins']; } }
        if (isset($p['enforce_local_group'])) { $z->enforce_local_group = ((string)$p['enforce_local_group'] ? '1' : '0'); if ($zo) { $zo->enforce_local_group = $z->enforce_local_group; } }
        if (isset($p['certificate'])) { $z->certificate = (string)$p['certificate']; if ($zo) { $zo->certificate = (string)$p['certificate']; } }
        // allowed lists
        if (isset($p['allowed_addresses'])) {
            $allowedAddrs = is_array($p['allowed_addresses']) ? $p['allowed_addresses'] : (strlen((string)$p['allowed_addresses']) ? [ (string)$p['allowed_addresses'] ] : []);
            if (isset($z->allowed_ip)) { unset($z->allowed_ip); }
            foreach ($allowedAddrs as $addr) { $addr = trim((string)$addr); if ($addr!=='') { $z->addChild('allowed_ip', $addr); } }
            if ($zo) {
                if (isset($zo->allowed_addresses)) { unset($zo->allowed_addresses); }
                if (!empty($allowedAddrs)) {
                    $aaNode = $zo->addChild('allowed_addresses');
                    foreach ($allowedAddrs as $addr) { $addr = trim((string)$addr); if ($addr!==''  ) { $aaNode->addChild('address', $addr); } }
                }
            }
        }
        if (isset($p['allowed_macs'])) {
            $allowedMacs = is_array($p['allowed_macs']) ? $p['allowed_macs'] : (strlen((string)$p['allowed_macs']) ? [ (string)$p['allowed_macs'] ] : []);
            if (isset($z->allowed_mac)) { unset($z->allowed_mac); }
            foreach ($allowedMacs as $mac) { $mac = trim((string)$mac); if ($mac!=='') { $z->addChild('allowed_mac', $mac); } }
            if ($zo) {
                if (isset($zo->allowed_macs)) { unset($zo->allowed_macs); }
                if (!empty($allowedMacs)) {
                    $amNode = $zo->addChild('allowed_macs');
                    foreach ($allowedMacs as $mac) { $mac = trim((string)$mac); if ($mac!=='') { $amNode->addChild('mac', $mac); } }
                }
            }
        }
        Config::getInstance()->save();
        $this->audit('cp.zone.ensure', ['name'=>$name,'created'=>false]);
        return ['status'=>'ok','created'=>false];
    }

    public function zoneGetAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $zone = trim((string)($p['zone'] ?? ''));
        if ($zone === '') { return ['status'=>'error','message'=>'missing zone']; }
        $cp = $this->getCpNode();
        $opn = $this->getOpnsenseCpNode();
        $z = $this->findZone($cp, $zone);
        $zo = $this->findOpnZone($opn, $zone);
        if ($z === null) { return ['status'=>'error','message'=>'zone not found']; }
        $ifs = [];
        foreach ((array)($z->interface ?? []) as $i) { $ifs[] = (string)$i; }
        if ($zo && isset($zo->interfaces)) {
            foreach ((array)$zo->interfaces->interface as $i) { $ifs[] = (string)$i; }
            $ifs = array_values(array_unique($ifs));
        }
        return [
            'zone' => [
                'name' => (string)$z->name,
                'description' => (string)($zo->description ?? $z->description ?? $z->descr ?? ''),
                'enabled' => (string)($zo->enabled ?? $z->enable ?? '0'),
                'interfaces' => $ifs,
                'auth' => [ 'method' => (string)($z->authmethod ?? ''), 'server' => (string)($z->authserver ?? '') ],
                'accounting' => [ 'enabled' => (string)($zo->accounting ?? $z->accounting ?? '0'), 'interval' => (string)($zo->accounting_interval ?? $z->accounting_interval ?? '') ],
                'zoneid' => (string)($zo->zoneid ?? $z->zoneid ?? ''),
            ]
        ];
    }

    public function zoneAuthAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $zone = trim((string)($p['zone'] ?? ''));
        $method = strtolower(trim((string)($p['method'] ?? '')));
        $server = trim((string)($p['server'] ?? ''));
        if ($zone==='' || $method==='') { return ['status'=>'error','message'=>'missing zone/method']; }
        $cp = $this->getCpNode();
        $opn = $this->getOpnsenseCpNode();
        $z = $this->findZone($cp, $zone);
        $zo = $this->findOpnZone($opn, $zone);
        if ($z === null) { return ['status'=>'error','message'=>'zone not found']; }
        if (!in_array($method, ['radius','local'], true)) { return ['status'=>'error','message'=>'unsupported auth method']; }
        if ($method === 'radius') {
            if ($server === '' || !$this->authServerExists($server)) { return ['status'=>'error','message'=>'radius server not found']; }
            $z->authserver = $server;
            if ($zo) { $zo->authserver = $server; }
        } else {
            unset($z->authserver);
            if ($zo && isset($zo->authserver)) { unset($zo->authserver); }
        }
        $z->authmethod = $method; // set after validation
        if ($zo) { $zo->authmethod = $method; }
        Config::getInstance()->save();
        $this->audit('cp.zone.auth', ['zone'=>$zone,'method'=>$method,'server'=>$server]);
        return ['status'=>'ok'];
    }

    public function zoneAccountingAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $zone = trim((string)($p['zone'] ?? ''));
        $enabled = (string)($p['enabled'] ?? '0');
        $interval = (string)($p['interval'] ?? '300');
        if ($zone==='') { return ['status'=>'error','message'=>'missing zone']; }
        if (!ctype_digit($interval)) { return ['status'=>'error','message'=>'invalid interval']; }
        $cp = $this->getCpNode();
        $opn = $this->getOpnsenseCpNode();
        $z = $this->findZone($cp, $zone);
        $zo = $this->findOpnZone($opn, $zone);
        if ($z === null) { return ['status'=>'error','message'=>'zone not found']; }
        $z->accounting = ($enabled ? '1' : '0');
        $z->accounting_interval = $interval;
        if ($zo) { $zo->accounting = ($enabled ? '1' : '0'); $zo->accounting_interval = $interval; }
        Config::getInstance()->save();
        $this->audit('cp.zone.accounting', ['zone'=>$zone,'enabled'=>$enabled,'interval'=>$interval]);
        return ['status'=>'ok'];
    }

    public function zoneEnableAction(): array
    {
        $zone = trim((string)($this->request->getPost('zone') ?? $this->request->get('zone') ?? ''));
        $enabled = (string)($this->request->getPost('enabled') ?? $this->request->get('enabled') ?? '1');
        if ($zone==='') { return ['status'=>'error','message'=>'missing zone']; }
        $cp = $this->getCpNode();
        $opn = $this->getOpnsenseCpNode();
        $z = $this->findZone($cp, $zone);
        $zo = $this->findOpnZone($opn, $zone);
        if ($z === null) { return ['status'=>'error','message'=>'zone not found']; }
        $z->enable = ($enabled ? '1' : '0');
        if ($zo) { $zo->enabled = ($enabled ? '1' : '0'); }
        Config::getInstance()->save();
        $this->audit('cp.zone.enable', ['zone'=>$zone,'enabled'=>$enabled]);
        return ['status'=>'ok'];
    }

    public function bypassSetAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $zone = trim((string)($p['zone'] ?? ''));
        $ip = trim((string)($p['ip'] ?? ''));
        $mac = trim((string)($p['mac'] ?? ''));
        $enabled = (string)($p['enabled'] ?? '1');
        if ($zone==='') { return ['status'=>'error','message'=>'missing zone']; }
        if ($ip==='' && $mac==='') { return ['status'=>'error','message'=>'ip or mac required']; }
        if ($ip!=='' && !$this->isValidIp($ip)) { return ['status'=>'error','message'=>'invalid ip']; }
        if ($mac!=='' and !$this->isValidMac($mac)) { return ['status'=>'error','message'=>'invalid mac']; }
        $cp = $this->getCpNode();
        $z = $this->findZone($cp, $zone);
        if ($z === null) { return ['status'=>'error','message'=>'zone not found']; }
        if ($enabled) {
            if ($ip!=='') {
                $exists = false; foreach ((array)($z->allowed_ip ?? []) as $n) { if ((string)$n === $ip) { $exists = true; break; } }
                if (!$exists) { $z->addChild('allowed_ip', $ip); }
            }
            if ($mac!=='') {
                $exists = false; foreach ((array)($z->allowed_mac ?? []) as $n) { if (strtolower((string)$n) === strtolower($mac)) { $exists = true; break; } }
                if (!$exists) { $z->addChild('allowed_mac', $mac); }
            }
        } else {
            if ($ip!=='' && isset($z->allowed_ip)) {
                $idx=0; foreach ($z->allowed_ip as $n) { if ((string)$n === $ip) { unset($z->allowed_ip[$idx]); break; } $idx++; }
            }
            if ($mac!=='' and isset($z->allowed_mac)) {
                $idx=0; foreach ($z->allowed_mac as $n) { if (strtolower((string)$n) === strtolower($mac)) { unset($z->allowed_mac[$idx]); break; } $idx++; }
            }
        }
        Config::getInstance()->save();
        $this->audit('cp.bypass.set', ['zone'=>$zone,'ip'=>$ip,'mac'=>$mac,'enabled'=>$enabled]);
        return ['status'=>'ok'];
    }

    public function applyAction(): array
    {
        @exec('/usr/local/sbin/configctl captiveportal restart 2>/dev/null', $out, $rc);
        if ($rc !== 0) { return ['status'=>'error','message'=>'captiveportal restart failed']; }
        $this->audit('cp.apply', []);
        return ['status'=>'ok'];
    }
}


