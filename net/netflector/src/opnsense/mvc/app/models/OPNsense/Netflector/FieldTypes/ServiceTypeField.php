<?php

/*
 * Copyright (C) 2026 cayossarian (Bill Flood)
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
 * A list of DNS-SD service types (_ipp._tcp). Core has no such type, and a hostname lets
 * printer.local by. The daemon ignores case, a trailing .local and a trailing dot, so a type
 * spelled twice that way is a repeat it refuses.
 */
class ServiceTypeField extends BaseSetField
{
    protected function defaultValidationMessage()
    {
        return gettext('[%s] is not a DNS-SD service type, such as _ipp._tcp.');
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->isSet()) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                $seen = [];
                foreach ($this->iterateInput($data) as $type) {
                    if (!preg_match('/^_[0-9a-z_-]{1,62}\._(tcp|udp)(\.local)?\.?$/iD', $type)) {
                        return [$this->getValidationMessage($type)];
                    }
                    $key = strtolower(preg_replace('/(\.local)?\.?$/iD', '', $type));
                    if (str_starts_with($key, '_dns-sd.')) {
                        return [sprintf(gettext('[%s] names DNS-SD\'s own records, not a service.'), $type)];
                    }
                    if (isset($seen[$key])) {
                        return [sprintf(
                            gettext('[%s] repeats [%s]: list each service type once.'),
                            $type,
                            $seen[$key]
                        )];
                    }
                    $seen[$key] = $type;
                }
                return [];
            }]);
        }
        return $validators;
    }
}
