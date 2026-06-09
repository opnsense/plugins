<?php

namespace OPNsense\Cloudflared\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = 'OPNsense\Cloudflared\Cloudflared';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceTemplate = 'OPNsense/Cloudflared';
    protected static $internalServiceName = 'cloudflared';

    /**
     * Reconfigura o serviço: cria diretórios, recarrega templates,
     * aplica sysctl tunables e reinicia o serviço.
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend->configdRun("cloudflared reconfigure");
            return ['status' => 'ok'];
        }
        return ['status' => 'failed'];
    }

    public function tunnelStatusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("cloudflared tunnel_status");
        $data = json_decode(trim($response), true);
        if ($data === null) {
            return ['tunnel' => 'unknown'];
        }
        return $data;
    }

    public function installAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("cloudflared install_binary");
            if ($response === null) {
                return ['response' => 'ERROR: configd did not respond. Run "service configd restart" on OPNsense.'];
            }
            $response = trim($response);
            if ($response === '' || $response === 'FAILED') {
                return ['response' => 'ERROR: Action not found. Run "service configd restart" on OPNsense to reload actions.'];
            }
            return ['response' => $response];
        }
        return ['response' => 'error'];
    }
}
