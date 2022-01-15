<?php

/*
* Copyright (C) 2022 Manuel Faux <mfaux@conf.at>
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
use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex as RegexValidator;

/**
 * Check a string for a valid PREG regex pattern when the matchtype
 * field is set to expect a regex (e.g. '~' or '~*').
 *
 * Class NgxRegexPatternConstraint
 * @package OPNsense\Nginx\Constraints
 */
class NgxRegexPatternConstraint extends BaseConstraint
{
    public function validate(Validation $validator, $attribute): bool
    {
        $node = $this->getOption('node');
        if ($node) {
            $value = (string)$node;
            $parentNode = $node->getParentNode();
            $matchtype = $this->getOption('matchtype');

            // Validate regex
            if (!empty((string)$parentNode->$matchtype) && ((string)$parentNode->$matchtype)[0] == '~') {
                $value = str_replace('#', '\#', $value);
                if (@preg_match("#$value#", '') === false) {
                    $validator->appendMessage(new Message(gettext("Valid regular expression expected"), $attribute));
                }
            }
        }

        return true;
    }
}
