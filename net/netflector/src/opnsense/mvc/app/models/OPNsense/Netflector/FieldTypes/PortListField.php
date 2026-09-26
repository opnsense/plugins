<?php

/*
 * Copyright (C) 2026 Sergii Bogomolov
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

namespace OPNsense\Netflector\FieldTypes;

use OPNsense\Base\FieldTypes\BaseSetField;
use OPNsense\Base\Validators\CallbackValidator;

/**
 * A list of UDP ports, each 1-65535. Not a PortField: that holds one port and, with Multiple set to
 * hold several, sends the client all 65535 options. Digits only and no leading zero.
 */
class PortListField extends BaseSetField
{
    public function setValue($value)
    {
        $items = array_map('trim', explode($this->internalFieldSeparator, (string)$value));
        parent::setValue(implode($this->internalFieldSeparator, array_unique($items)));
    }

    protected function defaultValidationMessage()
    {
        return gettext('Please specify valid port numbers (1-65535).');
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->isSet()) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                foreach ($this->iterateInput($data) as $port) {
                    if (!ctype_digit($port) || str_starts_with($port, '0') || (int)$port > 65535) {
                        return [$this->getValidationMessage()];
                    }
                }
                return [];
            }]);
        }
        return $validators;
    }
}
