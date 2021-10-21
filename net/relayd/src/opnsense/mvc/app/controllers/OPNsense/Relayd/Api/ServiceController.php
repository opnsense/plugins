<?php

/*
 * Copyright (C) 2018 EURO-LOG AG
 * Copyright (c) 2021 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Relayd\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Relayd\Relayd;

/**
 * Class ServiceController
 * @package OPNsense\relayd
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Relayd\Relayd';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceTemplate = 'OPNsense/Relayd';
    protected static $internalServiceName = 'relayd';
    private $internalLockHandle = null;

    /**
     * simple lock mechanism
     */
    private function lock($release = null)
    {
        if ($release != null) {
            flock($this->internalLockHandle, LOCK_UN);
            fclose($this->internalLockHandle);
            return true;
        }

        $this->internalLockHandle = fopen("/tmp/relayd.lock", "w+");
        if ($this->internalLockHandle != null && flock($this->internalLockHandle, LOCK_EX)) {
            return true;
        }
        return false;
    }

    /**
     * test relayd configuration
     * @return array
     */
    public function configtestAction()
    {
        if ($this->request->isPost()) {
            $result['status'] = 'ok';
            $this->sessionClose();

            $backend = new Backend();

            $result['function'] = "configtest";
            $result['template'] = trim($backend->configdRun('template reload OPNsense/Relayd'));
            if ($result['template'] != 'OK') {
                $result['result'] = "Template error: " . $result['template'];
                return $result;
            }
            $result['result'] = trim($backend->configdRun('relayd configtest'));
            return $result;
        } else {
            return array('status' => 'failed');
        }
    }

    /**
     * reconfigure relayd
     * @return array
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            if ($this->lock()) {
                $this->sessionClose();
                $result['function'] = "reconfigure";
                $result['status'] = 'failed';
                $backend = new Backend();
                $status = $this->statusAction();
                if (!empty((string)$this->getModel()->general->enabled)) {
                    $result = $this->configtestAction();
                    if ($result['template'] == 'OK' && preg_match('/configuration OK$/', $result['result']) == 1) {
                        if ($status['status'] != 'running') {
                            $result['result'] = trim($backend->configdRun('relayd start'));
                        } else {
                            $result['result'] = trim($backend->configdRun('relayd reload'));
                        }
                    } else {
                        return $result;
                    }
                } else {
                    if ($status['status'] == 'running') {
                        $result['result'] = trim($backend->configdRun('relayd stop'));
                    }
                }
                $this->lock(1);
                if ($this->getModel()->configClean()) {
                    $result['status'] = 'ok';
                }
                return $result;
            } else {
                throw new \Exception("Cannot get lock");
            }
        } else {
            return array('status' => 'failed');
        }
    }

    /**
     * avoid restarting Relayd on reconfigure
     */
    protected function reconfigureForceRestart()
    {
        return 0;
    }
}
