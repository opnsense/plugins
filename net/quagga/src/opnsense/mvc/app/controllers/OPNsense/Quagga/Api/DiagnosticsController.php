<?php
/**
 *    Copyright (C) 2017 Frank Wall
 *    Copyright (C) 2017 Michael Muenz
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Quagga\Api;
use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Quagga\Diagnostics;
/**
 * Class DiagnosticsController
 * @package OPNsense\Quagga
 */
class DiagnosticsController extends ApiControllerBase
{
    /**
     * show ip bgp
     * @return array
     */
    public function showipbgpAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("quagga diag-bgp2", true);
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }
    /**
     * show ip bgp summary
     * @return array
     */
    public function showipbgpsummaryAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("quagga diag-bgp summary", true);
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }
/*    public function statusAction()
*    {
*        $backend = new Backend();
*        $model = new AcmeClient();
*        $response = $backend->configdRun("acmeclient http-status");
*        if (strpos($response, "not running") > 0) {
*            if ($model->settings->enabled->__toString() == 1) {
*                $status = "stopped";
*            } else {
*                $status = "disabled";
*            }
*        } elseif (strpos($response, "is running") > 0) {
*            $status = "running";
*        } elseif ($model->settings->enabled->__toString() == 0) {
*            $status = "disabled";
*        } else {
*            $status = "unkown";
*        }
*        return array("status" => $status);
*    } */
}
