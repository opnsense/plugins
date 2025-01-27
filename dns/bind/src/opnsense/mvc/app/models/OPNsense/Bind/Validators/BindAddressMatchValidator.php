<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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

namespace OPNsense\Bind\Validators;

use OPNsense\Base\BaseValidator;
use OPNsense\Firewall\Util;
use OPNsense\Base\Messages\Message;

/**
 * Class NetworkValidator validate networks and ip addresses
 * @package OPNsense\Base\Validators
 */
class BindAddressMatchValidator extends BaseValidator
{
    /**
     * @var array List of built in ACLs
     */
    protected $builtinAcls = ['any','none','localhost','localnets'];

    /**
     * Executes network / ip validation for a subset of Bind Address Match Lists
     *      version         : ipv4, ipv6, all (default)
     *      netMaskAllowed  : true (default), false)
     *      netMaskRequired : true, false (default)
     *      strict:         : true, false (default)
     *
     * Address match list elements which are supported:
     *  - ip_address: an IP address (IPv4 or IPv6)
     *  - netprefix: an IP prefix (in / notation)
     *  - negation with a leading exclamation mark (!)
     *  - built in ACL names of: any, none, localhost, and localnets
     *
     * Address match list elements which are NOT supported:
     *  - server_key: a key ID, as defined by the key statement
     *  - acl_name: the name of an address match list defined with the acl statement
     *  - a nested address match list enclosed in braces
     *
     * Reference: https://bind9.readthedocs.io/en/v9.20.4/reference.html#address-match-lists
     *
     * @param $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate($validator, $attribute): bool
    {
        $result = true;
        $msg = $this->getOption('message');
        $fieldSplit = $this->getOption('split', null);
        if ($fieldSplit == null) {
            $values = array($validator->getValue($attribute));
        } else {
            $values = explode($fieldSplit, $validator->getValue($attribute));
        }
        foreach ($values as $value) {
            // strip off negation before address validation
            $value = ltrim($value, '!');

            // short-circuit on built-in ACLs
            if (in_array($value, $this->builtinAcls)) {
                continue;
            }

            // parse filter options
            $filterOpt = 0;
            switch (strtolower($this->getOption('version') ?? '')) {
                case "ipv4":
                    $filterOpt |= FILTER_FLAG_IPV4;
                    break;
                case "ipv6":
                    $filterOpt |= FILTER_FLAG_IPV6;
                    break;
                default:
                    $filterOpt |= FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
            }

            // split network
            if (strpos($value, "/") !== false) {
                if ($this->getOption('netMaskAllowed') === false) {
                    $result = false;
                } else {
                    $cidr = $value;
                    $parts = explode("/", $value);
                    if (count($parts) > 2 || !ctype_digit($parts[1])) {
                        // more parts then expected or second part is not numeric
                        $result = false;
                    } else {
                        $mask = $parts[1];
                        $value = $parts[0];
                        if (strpos($parts[0], ":") !== false) {
                            // probably ipv6, mask must be between 0..128
                            if ($mask < 0 || $mask > 128) {
                                $result = false;
                            }
                        } else {
                            // most likely ipv4 address, mask must be between 0..32
                            if ($mask < 0 || $mask > 32) {
                                $result = false;
                            }
                        }
                    }

                    if ($this->getOption('strict') === true && !Util::isSubnetStrict($cidr)) {
                        $result = false;
                    }
                }
            } elseif ($this->getOption('netMaskRequired') === true) {
                $result = false;
            }


            if (filter_var($value, FILTER_VALIDATE_IP, $filterOpt) === false) {
                $result = false;
            }

            if (!$result) {
                // append validation message
                $validator->appendMessage(new Message($msg, $attribute, 'BindAddressMatchValidator'));
            }
        }

        return $result;
    }
}
