<?php

/**
 *    Copyright (C) 2017-2024 Frank Wall
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
use OPNsense\Base\Messages\Message;
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

    /**
     * check if the specfied package is installed
     * @param $name package name
     * @return bool is the package installed
     */
    public function isPackageInstalled($name)
    {
        $backend = new Backend();
        $_package_list = $backend->configdRun('firmware local');
        if (preg_match("/^$name\|\|.*/m", $_package_list)) {
            return 1;
        }
        return 0;
    }

    /**
     * check if the specified string is a TSIG key statement in named.conf
     * format as written by tsig-keygen, e.g.
     *   key "name" { algorithm hmac-sha256; secret "base64=="; };
     * @param $data string key file content
     * @return bool is the key file valid
     */
    public static function isValidNsupdateKey($data)
    {
        // strip comments (#, // and /* */) which are allowed in named.conf
        $data = preg_replace(['/\/\*.*?\*\//s', '/(#|\/\/)[^\n]*/'], '', $data);

        if (
            !preg_match(
                '/^\s*key\s+("[^"\s]+"|[^"\s{};]+)\s*\{([^{}]*)\}\s*;\s*$/s',
                $data,
                $matches
            )
        ) {
            return false;
        }

        // the key statement must contain exactly one algorithm and one secret
        $options = [];
        foreach (explode(';', $matches[2]) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            if (preg_match('/^algorithm\s+("?)[a-zA-Z0-9.-]+\1$/', $statement)) {
                $options[] = 'algorithm';
            } elseif (preg_match('/^secret\s+"[A-Za-z0-9+\/=\s]+"$/', $statement)) {
                $options[] = 'secret';
            } else {
                return false;
            }
        }
        sort($options);

        return $options === ['algorithm', 'secret'];
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        foreach ($this->validations->validation->iterateItems() as $validation) {
            // only validate changed entries to not break existing configurations
            if (
                !$validateFullModel &&
                !$validation->method->isFieldChanged() &&
                !$validation->dns_service->isFieldChanged() &&
                !$validation->dns_nsupdate_key->isFieldChanged()
            ) {
                continue;
            }
            if (
                !$validation->method->isEqual('dns01') ||
                !$validation->dns_service->isEqual('dns_nsupdate')
            ) {
                continue;
            }
            // an empty key is valid, nsupdate will then send unsigned updates
            $key = trim((string)$validation->dns_nsupdate_key);
            if ($key !== '' && !self::isValidNsupdateKey($key)) {
                $messages->appendMessage(
                    new Message(
                        sprintf(
                            gettext('Expected a full TSIG key statement (key name, algorithm, secret), e.g. %s'),
                            'key "acme-update-key" { algorithm hmac-sha256; secret "..."; };'
                        ),
                        $validation->__reference . '.dns_nsupdate_key'
                    )
                );
            }
        }

        return $messages;
    }
}
