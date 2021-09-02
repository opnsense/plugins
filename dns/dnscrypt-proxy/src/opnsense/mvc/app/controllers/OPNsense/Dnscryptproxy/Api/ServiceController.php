<?php

/**
 *    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Dnscryptproxy\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Dnscryptproxy;
use OPNsense\Dnscryptproxy\Settings;

/**
 * An ApiMutableServiceControllerBase based class which is used to control
 * the dnscrypt-proxy service.
 *
 * @package OPNsense\Dnscryptproxy
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    /**
     * Reference the model class, which is used to determine if this service is
     * enabled (links the model to the service)
     *
     * @var string $internalServiceClass
     */
    protected static $internalServiceClass = '\OPNsense\Dnscryptproxy\Settings';

    /**
     * Before starting the service it will call configd to generate configuration
     * data, in this case it would execute the equivalent of configctl template
     * reload OPNsense/HelloWorld on the console
     *
     * @var string $internalServiceTemplate
     */
    protected static $internalServiceTemplate = 'OPNsense/Dnscryptproxy';

    /**
     * Which section of the model contains a boolean defining if the service is
     * enabled (settings.enabled)
     *
     * @var string $internalServiceEnabled
     */
    protected static $internalServiceEnabled = 'enabled';

    /**
     * Refers to the action template, where it can find
     * start/stop/restart/status/reload actions (actions_helloworld.conf)
     * @var string $internalServiceName
     */
    protected static $internalServiceName = 'dnscryptproxy';

    /**
     * This function creates an API endpoint for the reconfigure action.
     *
     * API endpoint:
     *   /api/dnscryptproxy/service/reconfigure
     *
     * This action is used to perform extra activites after a reconfigure
     * action is complete. We pass on the reconfigure result from the parent.
     *
     * @return array reconfigure() result or error message from configd.
     */
    public function reconfigureAction()
    {
        // Create a settings object to get some variables.
        $settings = new Settings();

        // Call the reconfigure action to save our settings.
        $reconfigure_result = parent::reconfigureAction();

        // Create a backend to run our activities.
        $backend = new Backend();
        $response = $backend->configdpRun($settings->configd_name . ' import-doh-client-certs');

        if ($response != "OK\n\n") {
            // Return an array containing a reponse for the message box to display.
            return array('status' => 'error', 'message' => $response);
        }

        return $reconfigure_result;
    }
}
