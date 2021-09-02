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

/**
 * An IndexController-based class that creates an endpoint to display the Log
 * page in the UI.
 *
 * @package OPNsense\Dnscryptproxy
 */
class LogsController extends \OPNsense\Base\IndexController
{
    /**
     * This function creates an endpoint in the UI for the Logs Controller.
     *
     * UI endpoint:
     *   /ui/dnscryptproxy/logs
     *
     * This is the default action when no parameters are provided.
     */
    public function indexAction()
    {
        // Create a settings object to get some variables.
        $settings = new Settings();

        // Create our own instance of a Controller to use getForm().
        $myController = new ControllerBase();

        $this->view->setVars(
            [
                'plugin_name' => $settings->api_name,
                'this_form' => $myController->getForm('logs'),
                // controllers/OPNsense/Dnscryptproxy/forms/logs.xml
            ]
        );

        // pick the template as the next view to render
        $this->view->pick('OPNsense/Dnscryptproxy/logs');
        // views/OPNsense/Dnscryptproxy/logs.volt
    }
}
