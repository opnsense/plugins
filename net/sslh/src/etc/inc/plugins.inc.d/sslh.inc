<?php

/*
    Copyright (C) 2021 agh1467 <7823088+agh1467@users.noreply.github.com>
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
 * Function to add plugin to the services page of OPNsense.
 *
 * This adds the service to the System: Diagnostics: Services page at
 * http://<opnsense>/status_services.php
 *
 * The suffix "_services" allows this function to be picked up by
 * etc/inc/plugins.inc:plugins_services() and be included in the
 * $services array to be processed by status_services.php.
 *
 * @return array the array of attributes for this service
*/
function sslh_services()
{

    // Create an array to be processed by www/status_services.php
    $service = array();
    // Load the plugin configuraiton file as an array for use below.
    $conf = parse_ini_file('/usr/local/opnsense/service/conf/sslh.conf', true);
    // Load in our settings to get the enabled state of the plugin.
    $settings = new \OPNsense\Sslh\Settings();

    // Only show the plugin if it's enabled.
    if (! ((string) $settings->enabled == '1')) {
        // return empty array if not enabled
        return $services;
    }

    $configd_name = $conf['plugin']['configd_name'];
    $service[] = array(
        'name' => $conf['plugin']['name'],                  // Service column
        'description' => gettext($conf['plugin']['label']), // Description column
        'configd' => array(                                 // Status column
            'restart' => array($configd_name . ' restart'),
            'start' => array($configd_name . ' start'),
            'stop' => array($configd_name . ' stop'),
        ),
        // 'pidfile' is used by etc/inc/util.inc:service_status(),
        // and displays service state in status column. If undefined,
        // the 'name' as defined above is used in is_process_running()
        // which must match the process name of the service in order to be found.
        'pidfile' => $conf['plugin']['pidfile'],
    );

    return $service;
}
