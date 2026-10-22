<?php

/*
 * Copyright (C) 2021-2026 Deciso B.V.
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

 namespace OPNsense\DynDNS;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;

/**
 * Class DynDNS
 * @package OPNsense\DynDNS
 */
class DynDNS extends BaseModel
{
    /**
     * fields required per AWS certificate based authentication method
     */
    private const AWS_AUTH_REQUIRED = [
        'rolesanywhere' => ['aws_certificate', 'aws_region', 'aws_trust_anchor_arn', 'aws_profile_arn', 'aws_role_arn'],
        'iot' => ['aws_certificate', 'aws_iot_endpoint', 'aws_iot_role_alias'],
    ];

    /**
     * @param string $refid certificate reference
     * @return bool true when the certificate exists and holds a private key
     */
    private function certificateHasKey($refid)
    {
        foreach (Config::getInstance()->object()->cert as $cert) {
            if ((string)$cert->refid === $refid) {
                return !empty((string)$cert->prv);
            }
        }
        return false;
    }

    /**
     * validate AWS certificate based authentication settings
     * @param string $key account reference
     * @param \OPNsense\Base\FieldTypes\BaseField $node account node
     * @param \OPNsense\Base\Messages\MessageCollection $messages validation messages
     */
    private function validateAwsAuth($key, $node, $messages)
    {
        $method = (string)$node->aws_auth;
        if (!$node->service->isEqual('aws') || !isset(self::AWS_AUTH_REQUIRED[$method])) {
            return;
        }
        foreach (self::AWS_AUTH_REQUIRED[$method] as $field) {
            if ($node->$field->isEmpty()) {
                $messages->appendMessage(
                    new Message(gettext('This field is required for the selected authentication.'), $key . '.' . $field)
                );
            }
        }
        if (!$node->aws_certificate->isEmpty() && !$this->certificateHasKey((string)$node->aws_certificate)) {
            $messages->appendMessage(
                new Message(gettext('The selected certificate has no private key.'), $key . '.aws_certificate')
            );
        }
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $validate_servers = [];
        $validate_aws = [];

        foreach ($this->getFlatNodes() as $key => $node) {
            $tagName = $node->getInternalXMLTagName();
            $parentNode = $node->getParentNode();
            if ($validateFullModel || $node->isFieldChanged()) {
                if ($parentNode->getInternalXMLTagName() === 'account' && in_array($tagName, ['protocol', 'server'])) {
                    $parentKey = $parentNode->__reference;
                    $validate_servers[$parentKey] = $parentNode;
                }
                if (
                    $parentNode->getInternalXMLTagName() === 'account' &&
                    ($tagName === 'service' || str_starts_with($tagName, 'aws_'))
                ) {
                    $validate_aws[$parentNode->__reference] = $parentNode;
                }
            }
        }

        foreach ($validate_aws as $key => $node) {
            $this->validateAwsAuth($key, $node, $messages);
        }

        foreach ($validate_servers as $key => $node) {
            $validate_url = false;

            if ($node->service->isEqual('powerdns')) {
                $validate_url = true;
            } elseif (!$node->service->isEqual('custom')) {
                continue;
            }

            $srv = (string)$node->server;

            if (in_array((string)$node->protocol, ['get', 'post', 'put']) || $validate_url) {
                if (empty($srv) || filter_var($srv, FILTER_VALIDATE_URL) === false) {
                    $messages->appendMessage(
                        new Message(
                            gettext('A valid URI is required.'),
                            $key . '.server'
                        )
                    );
                }
            } else {
                if (empty($srv) || filter_var($srv, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                    $messages->appendMessage(
                        new Message(
                            gettext('A valid domain is required.'),
                            $key . '.server'
                        )
                    );
                }
            }
        }

        return $messages;
    }
}
