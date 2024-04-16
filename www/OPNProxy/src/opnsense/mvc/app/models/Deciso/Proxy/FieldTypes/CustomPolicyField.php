<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace Deciso\Proxy\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Core\Config;

/**
 * Class UserGroupField
 */
class CustomPolicyField extends BaseField
{
    protected $internalIsContainer = false;
    protected $internalValidationMessage = "invalid domain and path combination";
    private $separatorchar = "\n";

    /**
     * split and yield items
     * @param array $data to validate
     * @return \Generator
     */
    private function getItems($data)
    {
        foreach (explode($this->separatorchar, trim($data)) as $value) {
            yield $value;
        }
    }

    /**
     * retrieve field validators for this field type
     * @return array
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                $messages = array();
                foreach ($this->getItems($data) as $item) {
                    $parts = explode("/", $item, 2);
                    $domain = substr($parts[0], 0, 1) == "." ? substr($parts[0], 1) : $parts[0];
                    if ($item == "*") {
                        // explicit wildcard
                        continue;
                    } elseif (
                        filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false &&
                        filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) === false
                    ) {
                        $messages[] = sprintf(
                            gettext('Entry "%s" does not contain a valid domain or address.'),
                            $item
                        );
                    } elseif (filter_var("https://{$domain}", FILTER_VALIDATE_URL) === false) {
                        $messages[] = sprintf(
                            gettext('Entry "%s" does not contain a valid path.'),
                            $item
                        );
                        continue;
                    }
                }
                return $messages;
            }
            ]);
        }
        return $validators;
    }
}
