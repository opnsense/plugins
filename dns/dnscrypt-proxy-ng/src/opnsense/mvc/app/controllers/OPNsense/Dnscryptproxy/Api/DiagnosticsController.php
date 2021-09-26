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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Dnscryptproxy\Settings;

/**
 * An ApiControllerBase class used to perform various diagnostics for
 * dnscrypt-proxy.
 *
 * This class includes the following API actions:
 *
 * `command`
 *
 * This API is accessible at the following URL endpoint:
 *
 * `/api/dnscryptproxy/diagnostics`
 *
 * @package OPNsense\Dnscryptproxy
 */
class DiagnosticsController extends ApiControllerBase
{
    /**
     * Function indexAction() is an API endpoint to call when no parameters are
     * provided for the API. Can be used to test the is working.

     * API endpoint:
     *   /api/dnscryptproxy/diagnostics
     *
     * Usage:
     *   /api/dnscryptproxy/diagnostics
     *
     * Returns an array which gets converted to json.
     *
     * @return array    includes status, saying everything is A-OK
     */
    public function indexAction()
    {
        return array('status' => 'ok');
    }

    /**
     * An API endpoint to execute pre-defined diagnostic commands.
     *
     * API endpoint:
     *   /api/dnscryptproxy/diagnostics/command
     *
     * Usage:
     *   /api/dnscryptproxy/diagnostics/command/show-certs
     *
     * Commands are accessible via the API call by including the desired command
     * after the API endpoint in the URL. The example above calls
     * commandAction() with the $target being "show-certs".
     *
     * The commands available are:
     *   resolve
     *   show-certs
     *   config-check
     *
     * @param  $target  string   command to execute, pre-defined in the function
     * @return          array    status, response (command output), maybe message
     */
    public function commandAction($target)
    {
        // Create a Settings class object to get a variable later.
        // (models/OPNsense/Dnscryptproxy/Settings.php)
        $settings = new Settings();

        // Create the results array for populating with info.
        $result = array();

        switch ($target) {
            case 'resolve':
                // Host name should be sent POST field 'command_input' in the API call.
                $hostname = $this->request->getPost('command_input', 'striptags');
                if (! empty($hostname)) {
                    // Perform hostname validation on the input to help mitigate injection.
                    if (filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                        $result['error'] = 'hostname validation failed';
                    } else {
                        // Only set the command if we have a hostname to use.
                        $configd_command = $settings->configd_name . ' resolve ' . $hostname;
                    }
                }

                break;
            case 'show-certs':
                $configd_command = $settings->configd_name . ' show-certs';

                break;
            case 'config-check':
                $configd_command = $settings->configd_name . ' config-check';

                break;
            case 'config-view':
                $filename = $this->request->getPost('command_input', 'striptags');
                $configd_command = $settings->configd_name . ' config-view ' . $filename;

                break;
            case 'version':
                $configd_command = $settings->configd_name . ' version';

                break;
        }

        // Ignore any calls that don't use defined target.
        if (isset($configd_command)) {
            // Establish a new connection to the backend for configd calls.
            $backend = new Backend();

            // Run the configd command, trim() for the whitespace (\n\n) at the end.
            $result['response'] = trim($backend->configdpRun($configd_command));
            // Since configdpRun() only returns a string of the output (without
            // an exitcode), we have to string match in order to to catch all of
            // the possible error conditions from configd, anything else should be OK.
            // Hopefully no successful output will look like these.
            if (
                $result['response'] != 'Action not found' and
                $result['response'] != 'Parameter mismatch' and
                $result['response'] != 'No command' and
                $result['response'] != 'No action type' and
                ! preg_match('/^Error \(\d*\)$/', $result['response']) and
                $result['response'] != 'Execute error' and
                $result['response'] != 'Unknown action type'
            ) {
                // If execution was OK, but no output was returned, we populate response ourself.
                if (empty($result['response'])) {
                    $result['response'] = '[Command returned no output]';
                }
                $result['status'] = 'ok';
            } else {
                // An error condition was detected in the reponse.
                $result['status'] = 'failed';
            }
        } else {
            // Inform the user that the target that they used not defined in thsi function.
            $result['error'] = 'target ' . $target . ' not defined';
            $result['status'] = 'failed';
        }
        // Finally return the whole $result array with reponse, and status.
        return $result;
    }
}
