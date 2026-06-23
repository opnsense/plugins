<?php

/*
 * Copyright (C) 2020-2024 Frank Wall
 * Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\AcmeClient;

use OPNsense\AcmeClient\AcmeClient;

/**
* Class LeValidationFactory
* @package OPNsense\AcmeClient
*/
class LeValidationFactory
{
    public const CONFIG_PATH = 'validations.validation';

    /**
     * create a LeValidation object from UUID
     * @param $uuid string UUID of configuration object
     * @return LeValidation|null LeValidation object or null if not found
     */
    public function getValidation(string $uuid)
    {
        // Ensure that the validation method can be found in config.
        $model = new \OPNsense\AcmeClient\AcmeClient();
        $obj = $model->getNodeByReference(self::CONFIG_PATH . '.' . $uuid);
        if ($obj == null) {
            LeUtils::log_error("challenge type not found: {$uuid}");
            return null;
        }

        // Get type of validation to find the required class name.
        switch ((string)$obj->method) {
            case 'dns01':
                $search_name = $obj->dns_service;
                break;
            case 'http01':
                $search_name = "http_" . $obj->http_service;
                break;
            case 'tlsalpn01':
                $search_name = "tlsalpn_" . $obj->tlsalpn_service;
                break;
        }

        // Convert to PascalCase
        $val_name = str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', $search_name)));

        // Search class name
        foreach (glob(__DIR__ . "/LeValidation/*.php") as $filename) {
            $srv_found = basename($filename, '.php');
            try {
                $reflClass = new \ReflectionClass("OPNsense\\AcmeClient\\LeValidation\\{$srv_found}");
            } catch (\ReflectionException $e) {
                break;
            }
            if ($reflClass->implementsInterface('OPNsense\\AcmeClient\\LeValidationInterface')) {
                if ($srv_found == $val_name) {
                    // Create new object
                    $objVal = $reflClass->newInstance();
                    $objVal->setUuid($uuid);
                    return $objVal;
                }
            }
        }
        LeUtils::log_error("challenge type not supported: " . (string)$search_name . " ({$uuid})");
        return null;
    }
}
