<?php

/*
* Copyright (C) 2019 Fabian Franz
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
 * a very specific nginx check - not reusable
 *
 * Class NgxBusyBufferConstraint
 * @package OPNsense\Nginx\Constraints
 */
class NgxBusyBufferConstraint extends BaseConstraint
{
    public function validate($validator, $attribute): bool
    {
        $node = $this->getOption('node');
        if ($node) {
            $parentNode = $node->getParentNode();
            if (!$this->isEmpty($node)) {
                $proxy_buffer_size_node = $parentNode->proxy_buffer_size;
                $proxy_buffers_count_node = $parentNode->proxy_buffers_count;
                $proxy_buffers_size_node = $parentNode->proxy_buffers_size;
                $proxy_busy_buffers_size_node = $parentNode->proxy_busy_buffers_size;

                if (!$this->isEmpty($proxy_buffers_count_node) && !$this->isEmpty($proxy_buffers_size_node)) {
                    $proxy_buffers_count_int = intval((string) $proxy_buffers_count_node);
                    $proxy_buffers_size_int = intval((string) $proxy_buffers_size_node);

                    $proxy_buffers_total_minus1_size = ($proxy_buffers_count_int - 1) * $proxy_buffers_size_int;
                }

                if (!$this->isEmpty($proxy_busy_buffers_size_node)) {
                    $proxy_busy_buffers_size = intval((string) $proxy_busy_buffers_size_node);
                }

                if (!$this->isEmpty($proxy_buffer_size_node)) {
                    $proxy_buffer_size_int = intval((string) $proxy_buffer_size_node);
                }

                if (
                    isset($proxy_buffers_total_minus1_size) && isset($proxy_busy_buffers_size) &&
                    $proxy_buffers_total_minus1_size < $proxy_busy_buffers_size
                ) {
                    $validator->appendMessage(new Message(
                        gettext("Proxy Buffer Size must be less than the size of all Proxy Buffers minus one buffer."),
                        $attribute
                    ));
                }

                // nginx: [emerg] "proxy_busy_buffers_size" must be equal to or greater than the maximum of the value of "proxy_buffer_size" and one of the "proxy_buffers"
                if (
                    isset($proxy_busy_buffers_size) && isset($proxy_buffers_size_int) &&
                    $proxy_busy_buffers_size < $proxy_buffers_size_int
                ) {
                    $validator->appendMessage(new Message(
                        gettext("Proxy Busy Buffers Size must be equal to or greater than the maximum of one of the Proxy Buffers."),
                        $attribute
                    ));
                }

                // nginx: [emerg] "proxy_busy_buffers_size" must be equal to or greater than the maximum of the value of "proxy_buffer_size" and one of the "proxy_buffers"
                if (
                    isset($proxy_busy_buffers_size) && isset($proxy_buffer_size_int) &&
                    $proxy_busy_buffers_size < $proxy_buffer_size_int
                ) {
                    $validator->appendMessage(new Message(
                        gettext("Proxy Busy Buffers Size must be equal to or greater than the maximum of the value of Proxy Buffer Size."),
                        $attribute
                    ));
                }
            }
        }
        return true;
    }
}
