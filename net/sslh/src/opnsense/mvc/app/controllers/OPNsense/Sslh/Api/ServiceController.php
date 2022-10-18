<?php

/**
 *    Copyright (C) 2022 agh1467 <agh1467@protonmail.com>
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Sslh\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Sslh;

/**
 * An ApiMutableServiceControllerBase based class which is used to control
 * the sslh service.
 *
 * @package OPNsense\Sslh
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    /**
     * Reference the model class, which is used to determine if this service is
     * enabled (links the model to the service)
     *
     * @var string $internalServiceClass
     */
    protected static $internalServiceClass = '\OPNsense\Sslh\Settings';

    /**
     * Before starting the service it will call configd to generate configuration
     * data, in this case it would execute the equivalent of configctl template
     * reload OPNsense/Sslh on the console
     *
     * @var string $internalServiceTemplate
     */
    protected static $internalServiceTemplate = 'OPNsense/Sslh';

    /**
     * Which section of the model contains a boolean defining if the service is
     * enabled (settings.enabled)
     *
     * @var string $internalServiceEnabled
     */
    protected static $internalServiceEnabled = 'enabled';

    /**
     * Refers to the actions configuraiton file, where it can find
     * start/stop/restart/status/reload actions:
     * src/opnsense/service/actions.d/actions_sslh.conf
     * @var string $internalServiceName
     */
    protected static $internalServiceName = 'sslh';
}
