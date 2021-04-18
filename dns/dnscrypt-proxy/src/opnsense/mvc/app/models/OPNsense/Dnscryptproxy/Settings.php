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

namespace OPNsense\Dnscryptproxy;

use OPNsense\Base\BaseModel;

/**
 * Class Settings is a BaseModel class used when retriving model data
 * via getModel().
 *
 * Functionality of this class is inherited entirely from BaseModel.
 *
 * There are variables defined here that are used elsewhere to make code
 * more portable, and reduce typographic errors.
 *
 * @package OPNsense\Dnscryptproxy
 */
class Settings extends BaseModel
{
    /**
     * The name of this plugin.
     *
     * @var string $name
     */
    public $name = 'dnscrypt-proxy';

    /**
     * A label to use for this plugin.
     *
     * @var string $label
     */
    public $label = 'DNSCrypt Proxy';

    /**
     * The API endpoint name as created by Phalcon.
     *
     * @var string $api_name
     */
    public $api_name = 'dnscryptproxy';

    /**
     * The name to use to reference the service with configd.
     *
     * @var string $configd_name
     */
    public $configd_name = 'dnscryptproxy';
}
