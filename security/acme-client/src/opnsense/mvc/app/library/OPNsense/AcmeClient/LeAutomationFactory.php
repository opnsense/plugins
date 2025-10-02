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
* Class LeAutomationFactory
* @package OPNsense\AcmeClient
*/
class LeAutomationFactory
{
    public const CONFIG_PATH = 'actions.action';

    /**
     * create an automation object from a UUID
     * @param $uuid string UUID of the automation object
     * @return LeAutomation object or null if not found
     */
    public function getAutomation(string $uuid)
    {
        // Ensure that the automation can be found in config.
        $model = new \OPNsense\AcmeClient\AcmeClient();
        $obj = $model->getNodeByReference(self::CONFIG_PATH . '.' . $uuid);
        if ($obj == null) {
            LeUtils::log_error("automation not found: {$uuid}");
            return null;
        }

        // Convert to PascalCase, required to find the class name.
        $auto_name = str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', (string)$obj->type)));

        // Search class name
        foreach (glob(__DIR__ . "/LeAutomation/*.php") as $filename) {
            $file_found = basename($filename, '.php');
            try {
                $reflClass = new \ReflectionClass("OPNsense\\AcmeClient\\LeAutomation\\{$file_found}");
            } catch (\ReflectionException $e) {
                break;
            }
            if ($reflClass->implementsInterface('OPNsense\\AcmeClient\\LeAutomationInterface')) {
                if ($file_found == $auto_name) {
                    // Create new object
                    $objAuto = $reflClass->newInstance();
                    $objAuto->setUuid($uuid);
                    return $objAuto;
                }
            }
        }

        LeUtils::log_error("automation not supported: " . (string)$obj->type . " ({$uuid})");
        return null;
    }
}
