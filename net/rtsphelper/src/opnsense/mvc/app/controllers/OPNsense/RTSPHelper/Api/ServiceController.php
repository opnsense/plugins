<?php

namespace OPNsense\RTSPHelper\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rtsphelper start');
            return array("response" => $response);
        }
        return array("response" => array());
    }

    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rtsphelper stop');
            return array("response" => $response);
        }
        return array("response" => array());
    }

    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rtsphelper restart');
            return array("response" => $response);
        }
        return array("response" => array());
    }

    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('rtsphelper status');
        return array("status" => trim($response));
    }

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rtsphelper configure');
            return array("response" => $response);
        }
        return array("response" => array());
    }
}
