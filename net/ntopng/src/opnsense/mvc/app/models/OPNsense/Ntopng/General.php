<?php

/*
    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Ntopng;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class General extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);


        $http = (string)$this->httpport;
        $https = (string)$this->httpsport;

        if ($http === '' && $https === '') {
            $msg = gettext('Please input at least an HTTP or HTTPS port.');

            $messages->appendMessage(new Message(
                $msg,
                'httpport'
            ));

            $messages->appendMessage(new Message(
                $msg,
                'httpsport'
            ));
        }

        $addresses_length = count(explode(',', (string)$this->addresses));
        if ($addresses_length > 1 && $https !== '') {
            $messages->appendMessage(new Message(
                gettext(
                    "Can't have more then 1 listen address when using HTTPS"
                ),
                'addresses'
            ));

        }


        $redis_conn = (string)$this->redisconnection;

        if (trim($redis_conn) === '' && $redis_conn !== '') {
            $messages->appendMessage(new Message(
                gettext(
                    "Can't be all whitespace"
                ),
                'redisconnection'
            ));
        } else {
            if ($redis_conn !== ltrim($redis_conn)) {
                $messages->appendMessage(new Message(
                    gettext(
                        "Can't have leading whitespace"
                    ),
                    'redisconnection'
                ));
            }
            if ($redis_conn !== rtrim($redis_conn)) {
                $messages->appendMessage(new Message(
                    gettext(
                        "Can't have trailing whitespace"
                    ),
                    'redisconnection'
                ));
            }
        }

        return $messages;
    }
}
