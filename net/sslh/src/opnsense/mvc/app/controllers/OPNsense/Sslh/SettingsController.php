<?php

/*
    Copyright (C) 2022 agh1467 <agh1467@protonmail.com>
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

namespace OPNsense\Sslh;

/**
 * An IndexController-based class that creates an endpoint to display the Settings
 * page in the UI.
 *
 * @package OPNsense\Sslh
 */
class SettingsController extends \OPNsense\Base\IndexController
{
    /**
     * This function creates an endpoint in the UI for the Settings Controller.
     *
     * UI endpoint:
     * `/ui/sslh/settings`
     *
     * This is the default action when no parameters are provided.
     */
    public function indexAction()
    {
        // Set environment variables for within the Volt templates.
        $this->view->setVars([
            'plugin_name' => 'sslh',
            'api_name' => 'sslh',
            'this_form' => $this->getForm('settings'),
            // controllers/OPNsense/Sslh/forms/settings.xml
        ]);

        // pick the template as the next view to render
        $this->view->pick('OPNsense/Sslh/settings');
        // views/OPNsense/Sslh/settings.volt
    }
}
