<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class DhcpController extends ApiControllerBase
{
    use AuditTrait;
    use ValidationTrait;
    /**
     * Upsert static mapping into dhcpd config (interface section)
     * Params: iface, mac, ipaddr, descr
     */
    public function upsertAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $iface = trim((string)($p['iface'] ?? ''));
        $mac = strtolower(trim((string)($p['mac'] ?? '')));
        $ip = trim((string)($p['ipaddr'] ?? ''));
        $descr = trim((string)($p['descr'] ?? ''));
        if ($iface==='' || $mac==='' || $ip==='') {
            return ['status'=>'error','message'=>'missing iface/mac/ipaddr'];
        }
        if (!$this->isValidIface($iface)) { return ['status'=>'error','message'=>'invalid iface']; }
        if (!$this->isValidMac($mac)) { return ['status'=>'error','message'=>'invalid mac']; }
        if (!$this->isValidIp($ip)) { return ['status'=>'error','message'=>'invalid ipaddr']; }
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->dhcpd)) { $cfg->addChild('dhcpd'); }
        if (!isset($cfg->dhcpd->{$iface})) { $cfg->dhcpd->addChild($iface); }
        if (!isset($cfg->dhcpd->{$iface}->staticmap)) { $cfg->dhcpd->{$iface}->addChild('staticmap'); }

        $target = null;
        foreach ($cfg->dhcpd->{$iface}->staticmap as $sm) {
            if (strtolower((string)$sm->mac) === $mac) { $target = $sm; break; }
        }
        if ($target === null) {
            $target = $cfg->dhcpd->{$iface}->addChild('staticmap');
        }
        $target->mac = $mac;
        $target->ipaddr = $ip;
        if ($descr !== '') { $target->descr = $descr; }
        Config::getInstance()->save();
        $this->audit('dhcp.upsert', ['iface'=>$iface,'mac'=>$mac,'ipaddr'=>$ip]);
        return ['status'=>'ok'];
    }

    public function applyAction(): array
    {
        // restart dhcpd using configd action defined in actions_userprovision.conf
        @exec('/usr/local/sbin/configctl dhcpd restart 2>/dev/null', $out, $rc);
        if ($rc !== 0) { return ['status'=>'error','message'=>'dhcpd restart failed']; }
        $this->audit('dhcp.apply', []);
        return ['status'=>'ok'];
    }
}


