<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class General extends BaseModel
{
    /**
     * the remediation component (firewall bouncer) connects to a LAPI on another host
     * @return bool
     */
    public function bouncerUsesRemoteLapi(): bool
    {
        return $this->firewall_bouncer_enabled->isEqual('1') &&
            $this->bouncer_lapi->isEqual('remote') &&
            !$this->lapi_manual_configuration->isEqual('1');
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if (!$this->bouncerUsesRemoteLapi()) {
            return $messages;
        }

        if ($this->remote_lapi_url->isEmpty()) {
            $messages->appendMessage(new Message(
                gettext('A LAPI URL is required when the remediation component connects to a remote LAPI.'),
                $this->remote_lapi_url->getInternalXMLTagName()
            ));
        }

        // update-only field: getValue() is the stored key, the API never returns it
        if ($this->remote_bouncer_api_key->isEmpty()) {
            $messages->appendMessage(new Message(
                gettext('An API key is required when the remediation component connects to a remote LAPI.'),
                $this->remote_bouncer_api_key->getInternalXMLTagName()
            ));
        }

        return $messages;
    }
}
