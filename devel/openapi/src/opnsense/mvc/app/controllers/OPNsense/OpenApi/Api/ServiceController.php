<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\OpenApi\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\OpenApi
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceName = 'openapi';
    protected static $internalServiceClass = '\OPNsense\OpenApi\OpenApi';
    protected static $internalServiceTemplate = 'OPNsense/OpenApi';
    protected static $internalServiceEnabled = 'general.enabled';

    protected function reconfigureForceRestart()
    {
        return 0;
    }
    protected function invokeInterfaceRegistration()
    {
        return true;
    }
    // /**
    //  * check if service is enabled according to model
    //  */
    // protected function serviceEnabled()
    // {
    //     if (empty(static::$internalServiceEnabled)) {
    //         throw new \Exception('cannot check if service is enabled without internalServiceEnabled defined.');
    //     }

    //     return (string)($this->getModel())->getNodeByReference(static::$internalServiceEnabled) == '1';
    // }

    /**
     * reconfigure with optional stop, generate config and start / reload
     * @return array response message
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when model can't be instantiated
     */
    public function reconfigureAction()
    {
        try {
            $restart = $this->reconfigureForceRestart();
            $enabled = $this->serviceEnabled();
            var_dump($this->getModel());
            $backend = new Backend();

            if ($restart || !$enabled) {
                $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' stop');
            }

            if ($this->invokeInterfaceRegistration()) {
                $backend->configdRun('interface invoke registration');
            }

            // if (!empty(static::$internalServiceTemplate)) {
            //     $result = trim($backend->configdpRun('template reload', [static::$internalServiceTemplate]) ?? '');
            //     if ($result !== 'OK') {
            //         throw new UserException(sprintf(
            //             gettext('Template generation failed for internal service "%s". See backend log for details.'),
            //             static::$internalServiceName
            //         ), gettext('Configuration exception'));
            //     }
            // }

            // if ($enabled) {
            //     if ($restart || $this->statusAction()['status'] != 'running') {
            //         $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' start');
            //     } else {
            //         $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' reload');
            //     }
            // }

            return ['status' => $enabled];

        } catch (Exception $e) {
            return ['status' => $e->getMessage()];
        }

        return ['status' => 'failed'];
    }

}
