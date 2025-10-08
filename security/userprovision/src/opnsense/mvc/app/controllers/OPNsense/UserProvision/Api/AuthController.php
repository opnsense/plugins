<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class AuthController extends ApiControllerBase
{
    use AuditTrait;
    use ValidationTrait;

    /**
     * Generic RADIUS login proxy for alternative UIs (does not open CP session)
     * Params: server (authserver name), username, password
     */
    public function loginAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $serverName = trim((string)($p['server'] ?? ''));
        $username = trim((string)($p['username'] ?? ''));
        $password = (string)($p['password'] ?? '');
        if ($serverName === '' || $username === '' || $password === '') {
            return ['status'=>'error','message'=>'missing server/username/password'];
        }
        $cfg = Config::getInstance()->object();
        $srvConf = null;
        foreach ((array)($cfg->system->authserver ?? []) as $srv) {
            if ((string)($srv->name ?? '') === $serverName && (string)($srv->type ?? '') === 'radius') { $srvConf = $srv; break; }
        }
        if ($srvConf === null) { return ['status'=>'error','message'=>'radius server not found']; }
        $host = (string)($srvConf->host ?? '');
        $secret = (string)($srvConf->secret ?? '');
        $authPort = (string)($srvConf->auth_port ?? '1812');
        if ($host === '' || $secret === '') { return ['status'=>'error','message'=>'invalid radius config']; }

        // Use radclient if available
        $cmd = '/usr/bin/env radclient';
        @exec('which radclient 2>/dev/null', $outWhich, $rcWhich);
        if ($rcWhich !== 0) { $cmd = '/usr/local/bin/radclient'; }
        @exec('which ' . escapeshellarg($cmd) . ' 2>/dev/null', $dummy, $rcExist);
        if ($rcExist !== 0) {
            return ['status'=>'error','message'=>'radclient not available on system'];
        }
        $packet = "User-Name=$username\nUser-Password=$password\n";
        $target = escapeshellarg($host . ':' . $authPort);
        $secretArg = escapeshellarg($secret);
        $descriptorspec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = @proc_open($cmd . ' -x ' . $target . ' auth ' . $secretArg, $descriptorspec, $pipes);
        if (!is_resource($proc)) { return ['status'=>'error','message'=>'failed to run radclient']; }
        fwrite($pipes[0], $packet);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $rc = proc_close($proc);
        $ok = (strpos($stdout . $stderr, 'Access-Accept') !== false);
        $this->audit('auth.login', ['server'=>$serverName,'username'=>$username,'result'=>$ok?'accept':'reject']);
        return ['status'=>$ok?'ok':'reject','output'=>$stdout];
    }
}


