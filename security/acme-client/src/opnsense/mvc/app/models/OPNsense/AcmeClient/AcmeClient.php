<?php

/**
 *    Copyright (C) 2017 Frank Wall
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

namespace OPNsense\AcmeClient;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Backend;

/**
 * Class AcmeClient
 * @package OPNsense\AcmeClient
 */
class AcmeClient extends BaseModel
{
    /**
     * retrieve certificate by number
     * @param $certificateid certificate number
     * @return null|BaseField certificate details
     */
    public function getByCertificateID($certificateid)
    {
        foreach ($this->certificates->certificate->iterateItems() as $certificate) {
            if ((string)$certificateid === (string)$certificate->certificateid) {
                return $certificate;
            }
        }
        return null;
    }

    /**
     * check if module is enabled
     * @param $checkCertificates bool enable in-depth check (1 or more active certificates)
     * @return bool is the AcmeClient service enabled
     */
    public function isEnabled($checkCertificates = false)
    {
        if ((string)$this->settings->enabled === "1") {
            if ($checkCertificates === true) {
                foreach ($this->certificates->certificate->iterateItems() as $certificate) {
                    if ((string)$certificate->enabled == "1") {
                        return true; // Found a active certificate
                    }
                }
            } else {
                return true; // AcmeClient enabled
            }
        }
        return false;
    }

    /**
     * retrieve automation by number
     * @param $uuid action number
     * @return null|BaseField action details
     */
    public function getByActionID($uuid)
    {
        foreach ($this->actions->action->iterateItems() as $action) {
            if ((string)$uuid === (string)$action->getAttributes()["uuid"]) {
                return $action;
            }
        }
        return null;
    }

    /**
     * check if the specfied plugin is installed
     * @param $name plugin/package name
     * @return bool is the plugin installed
     */
    public function isPluginInstalled($name)
    {
        $backend = new Backend();
        return trim($backend->configdRun('firmware plugin ' . escapeshellarg($name)));
    }
}
