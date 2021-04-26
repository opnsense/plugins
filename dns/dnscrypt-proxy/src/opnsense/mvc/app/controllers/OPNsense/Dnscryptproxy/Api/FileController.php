<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
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
 */

namespace OPNsense\Dnscryptproxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Dnscryptproxy\Settings;

/**
 * This Controller extends ApiMutableModelControllerBase to create API endpoints
 * for file uploading and downloading.
 *
 * This API is accessiable at the following URL endpoint:
 *
 * `/api/dnscryptproxy/file`
 *
 * This class includes the following API endpoints:
 * ```
 * upload
 * download
 * remove
 *
 * ```
 *
 * @package OPNsense\Dnscryptproxy
 */
class FileController extends ApiControllerBase
{
    /**
     * Allows uploading a file using a specific pre-defined list of destination
     * files within the file system.
     *
     * API endpoint:
     *
     *   `/api/dnscryptproxy/file/set`
     *
     * Usage:
     *
     *   `/api/dnscryptproxy/set/settings.allowed_names_file_manual`
     *
     * This function only accepts specific `$target` variables to prevent user
     * manipulation through the API. It stores the file in a temporary location
     * and then a configd command executes a script which parses that file and
     * validates the contents, then copies that file to the pre-defined
     * destination.
     *
     * @return array          Array of the contents of the file.
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function uploadAction()
    {
        $settings = new Settings();

        // we only care about the content, the file name will be statically configured
        // this will reduce the need to manage the file system like cleaning up if a file name changes, etc.
        // it also mitigates file name length limitations of the file system.
        // it also mitigates risk of allowing the user to specify the file name
        if ($this->request->isPost() && $this->request->hasPost('content') && $this->request->hasPost('target')) {
            // Populate variables from the keys in the POST.
            $content = $this->request->getPost('content', 'striptags', '');
            $target = $this->request->getPost('target');

            // Check the content length so we have something to do.
            if (strlen($content) > 0 && ! is_null($target)) {
                // I looked for a better way to do this, but didn't find any.
                // Due to shell command length limitations it's risky to pass this
                // directly to configdRun(), and no way to get it to send the content
                // as stdin. So a second best is to write it to the file system
                // for read by an application afterwards. CaptivePortal does this method.
                if (
                    $target == 'settings.blocked_names_file_manual' ||
                    $target == 'settings.blocked_ips_file_manual' ||
                    $target == 'settings.allowed_names_file_manual' ||
                    $target == 'settings.allowed_ips_file_manual' ||
                    $target == 'settings.cloaking_file_manual'
                ) {
                    // create a temporary file name to use
                    $temp_filename = '/tmp/' . $settings->name . '_file_upload.tmp';
                    // let's put the file in /tmp
                    file_put_contents($temp_filename, $content);
                    $target_exp = explode('.', $target);

                    $backend = new Backend();
                    // Perform the import using configd. Executes a script which
                    // parses the content of the file for valid characters.
                    // If parse passes, the uploaded file is copied to the
                    // destination. Returns JSON of status and action.
                    $response = $backend->configdpRun(
                        $settings->configd_name . ' import-list ' . end($target_exp) . ' ' . $temp_filename
                    );

                    // If configd reports "Execute error," then $response is NULL.
                    // This can happen if there is a misconfiguration in the action (aka missing script/command).
                    if (! is_null($response)) {
                        return $response;
                    }

                    return array('error' => 'Error encountered', 'status' => 'Execute error');
                }

                return array('status' => 'error', 'message' => 'Unsupported target ' . $target);
            }

            return array('status' => 'error', 'message' => 'Missing target, or content.');
        }
    }

    /**
     * Calls the configd backend to retrive a pre-defined file, and return its
     * contents.
     *
     * API endpoint:
     *
     *   `/api/dnscryptproxy/file/get/settings.blocked_names_file_manual`
     *
     * Usage:
     *
     *   `/api/dnscryptproxy/get/`
     *
     * This function only accepts specific `$target` variables to prevent user
     * manipulation through the API. This should be the field ID of the calling
     * object. It will then execute the appropriate configd command, and return
     * the output from that command. The output is evaluated on the return to
     * detect an error condition.
     *
     * @param  string $target The desired pre-defined target for the API.
     * @return array          Array of the contents of the file.
     */
    public function downloadAction($target)
    {
        $settings = new Settings();

        if ($target != '') {
            if ($target == 'settings.blocked_names_file_manual') {
                $content_type = 'text';
                $filename = 'blocked-names-manual.txt';
            } elseif ($target == 'settings.blocked_ips_file_manual') {
                $content_type = 'text';
                $filename = 'blocked-ips-manual.txt';
            } elseif ($target == 'settings.allowed_names_file_manual') {
                $content_type = 'text';
                $filename = 'allowed-names-manual.txt';
            } elseif ($target == 'settings.allowed_ips_file_manual') {
                $content_type = 'text';
                $filename = 'allowed-ips-manual.txt';
            } elseif ($target == 'settings.cloaking_file_manual') {
                $content_type = 'text';
                $filename = 'cloaking-manual.txt';
            }
            if ($filename != '') {
                $backend = new Backend();
                $target_exp = explode('.', $target);
                $result = $backend->configdRun($settings->configd_name . ' export-' . end($target_exp));
                if ($result != null) {
                    $this->response->setRawHeader('Content-Type: ' . $content_type);
                    $this->response->setRawHeader('Content-Disposition: attachment; filename=' . $filename);

                    return $result;
                }
                // return empty response on error, maybe Throw?
                return '';
            }
        }
    }

    /**
     * Allows removing a file using a specific pre-defined list of target files
     * within the file system.
     *
     * API endpoint:
     *
     *   `/api/dnscryptproxy/file/remove`
     *
     * Usage:
     *
     *   `/api/dnscryptproxy/file/remove`
     *
     * Usage (Javascript):
     * ```
     * ajaxCall("{{ field['api']['remove'] }}",
     *          {'field': '{{ field['id'] }}'},
     *          function(data,status) {...
     * ```
     *
     * This function gets executed as part of clicking the remove button in the
     * UI. After the user accepts to removing the file, this is called to remove
     * the file from the file system.
     *
     * The function exects a key-value pair to be set with the name `field` with
     * the value being the field ID of the desired field.
     *
     * @return array          Array of the contents of the file.
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function removeAction()
    {
        $settings = new Settings();

        if ($this->request->isPost() && $this->request->hasPost('field')) {
            $field = $this->request->getPost('field');
            if ($field != '') {
                if (
                    $field == 'settings.blocked_names_file_manual' ||
                    $field == 'settings.blocked_ips_file_manual' ||
                    $field == 'settings.allowed_names_file_manual' ||
                    $field == 'settings.allowed_ips_file_manual' ||
                    $field == 'settings.cloaking_file_manual'
                ) {
                    $this->sessionClose();
                    $backend = new Backend();
                    $target_exp = explode('.', $field);
                    $response = array(
                        'status' => $backend->configdpRun(
                            $settings->configd_name . ' remove-' . end($target_exp)
                        ),
                    );
                    if (trim($response['status']) != 'OK') {
                        $response['error'] = 'Error encountered';
                    }

                    return json_encode($response, true);
                }

                return array('status' => 'error', 'message' => 'Unsupported field ' . $field);
            }

            return array('status' => 'error', 'message' => 'No field provided.');
        }
    }
}
