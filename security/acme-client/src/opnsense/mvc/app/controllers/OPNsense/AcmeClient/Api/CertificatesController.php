<?php

/**
 *    Copyright (C) 2017-2020 Frank Wall
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

namespace OPNsense\AcmeClient\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\AcmeClient\AcmeClient;

/**
 * Class CertificatesController
 * @package OPNsense\AcmeClient
 */
class CertificatesController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'acmeclient';
    protected static $internalModelClass = '\OPNsense\AcmeClient\AcmeClient';

    public function getAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('certificate', 'certificates.certificate', $uuid);
    }

    public function addAction()
    {
        return $this->addBase('certificate', 'certificates.certificate');
    }

    public function updateAction($uuid)
    {
        return $this->setBase('certificate', 'certificates.certificate', $uuid);
    }

    public function delAction($uuid)
    {
        # Remove the cert from list of certs known to acme.sh.
        $mdlAcme = new AcmeClient();
        if ($uuid != null) {
            $node = $mdlAcme->getNodeByReference('certificates.certificate.' . $uuid);
            if ($node != null) {
                $backend = new Backend();
                $response = $backend->configdRun("acmeclient remove-cert {$uuid}");
                // Give configd some time to start this operation before the
                // cert is removed from config.
                sleep(2);
            }
        }
        return $this->delBase('certificates.certificate', $uuid);
    }

    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('certificates.certificate', $uuid);
    }

    public function searchAction()
    {
        return $this->searchBase('certificates.certificate', array('enabled', 'name', 'altNames', 'description', 'lastUpdate', 'statusCode', 'statusLastUpdate'), 'name');
    }

    /**
     * sign certificate by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function signAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlAcme = new AcmeClient();

            if ($uuid != null) {
                $node = $mdlAcme->getNodeByReference('certificates.certificate.' . $uuid);
                if ($node != null) {
                    $backend = new Backend();
                    $response = $backend->configdRun("acmeclient sign-cert {$uuid}");
                    return array("response" => $response);
                }
            }
        }
        return $result;
    }

    /**
     * remove private key from certificate by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function removekeyAction($uuid)
    {
        $result = array("result" => "failed");
        $mdlAcme = new AcmeClient();
        if ($uuid != null) {
            $node = $mdlAcme->getNodeByReference('certificates.certificate.' . $uuid);
            if ($node != null) {
                $backend = new Backend();
                $response = $backend->configdRun("acmeclient remove-key {$uuid}");
            }
        }
        return $result;
    }

    /**
     * revoke certificate by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function revokeAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlAcme = new AcmeClient();

            if ($uuid != null) {
                $node = $mdlAcme->getNodeByReference('certificates.certificate.' . $uuid);
                if ($node != null) {
                    $backend = new Backend();
                    $response = $backend->configdRun("acmeclient revoke-cert {$uuid}");
                    return array("response" => $response);
                }
            }
        }
        return $result;
    }

    /**
     * rerun automation for the certificate by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function automationAction($uuid)
    {
        $result = array("result" => "failed");
        $mdlAcme = new AcmeClient();
        if ($uuid != null) {
            $node = $mdlAcme->getNodeByReference('certificates.certificate.' . $uuid);
            if ($node != null) {
                $backend = new Backend();
                $response = $backend->configdRun("acmeclient run-automation {$uuid}");
            }
        }
        return $result;
    }

    /**
     * (re-) import the certificate by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function importAction($uuid)
    {
        $result = array("result" => "failed");
        $mdlAcme = new AcmeClient();
        if ($uuid != null) {
            $node = $mdlAcme->getNodeByReference('certificates.certificate.' . $uuid);
            if ($node != null) {
                $backend = new Backend();
                $response = $backend->configdRun("acmeclient import {$uuid}");
            }
        }
        return $result;
    }
}
