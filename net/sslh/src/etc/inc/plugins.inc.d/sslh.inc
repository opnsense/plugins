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

/**
 * Function to register the plugin's service with OPNsense.
 *
 * This adds the service to the System: Diagnostics: Services page at
 * http://<opnsense>/ui/core/service
 *
 * The suffix "_services" allows this function to be picked up by
 * etc/inc/plugins.inc:plugins_services() and be included in the
 * $services array to be processed by /ui/core/service.
 *
 * @return array the array of attributes for this service
*/
function sslh_services()
{
    $service = array();

    // Load in our settings to get the enabled state of the plugin.
    $settings = new \OPNsense\Sslh\Settings();

    // Only show the plugin if it's enabled.
    if (! ((string) $settings->enabled == '1')) {
        // return empty array if not enabled
        return $service;
    }

    $configd_name = 'sslh';
    $service[] = array(
        'name' => 'sslh',                   // Service column
        'description' => gettext('SSLH'),   // Description column
        'configd' => array(                 // Status column
            'restart' => array($configd_name . ' restart'),
            'start' => array($configd_name . ' start'),
            'stop' => array($configd_name . ' stop'),
        ),
        'pidfile' => '/var/run/sslh.pid'
    );

    return $service;
}
