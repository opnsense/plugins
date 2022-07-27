<?php

/*
* Copyright (C) 2020 Manuel Faux
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

namespace OPNsense\Base\Constraints;

use Phalcon\Messages\Message;

/**
 * a very specific nginx check for Naxsi rule IDs - not reusable
 *
 * Class NaxsiIdentifierConstraint
 * @package OPNsense\Nginx\Constraints
 */
class NaxsiIdentifierConstraint extends BaseConstraint
{
    public function validate($validator, $attribute): bool
    {
        $node = $this->getOption('node');
        if ($node) {
            $parentNode = $node->getParentNode();

            // Validate whitelist IDs
            if ($parentNode->match_type == 'wl') {
                $vals = explode(",", (string)$node); // Whitelists can use several IDs
                $pos = 0;
                $neg = 0;

                // Check each ID
                foreach ($vals as $val) {
                    $intval = intval($val);

                    if (!is_numeric($val)) {
                        $validator->appendMessage(new Message(gettext("All rule IDs need to be numeric."), $attribute));
                    }
                    // 0 can only be used solely
                    elseif ($intval == 0 && count($vals) > 1) {
                        $validator->appendMessage(new Message(gettext("If ID 0 is specified, no other IDs can be listed."), $attribute));
                    } elseif ($intval < 0) {
                        $neg++;
                    } elseif ($intval > 0) {
                        $pos++;
                    }
                }

                // All IDs need to be positive or all negative
                if ($neg > 0 && $pos > 0) {
                    $validator->appendMessage(new Message(gettext("Negative and positive IDs cannot be mixed."), $attribute));
                }
            }
            // Validate rule IDs
            else {
                $val = (string)$node;
                if (!is_numeric($val)) {
                    // Did the user try to specify multiple IDs?
                    if (strpos($val, ',')) {
                        $validator->appendMessage(new Message(gettext("Rules can only have a single ID."), $attribute));
                    } else {
                        $validator->appendMessage(new Message(gettext("Rule IDs need to be numeric."), $attribute));
                    }
                }
                // Check that no internal ID was used
                elseif (intval($val) < 1000) {
                    $validator->appendMessage(new Message(gettext("Rule IDs lower than 1000 are reserved for internal rules."), $attribute));
                }
            }
        }

        return true;
    }
}
