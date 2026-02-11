<?php

/*
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Fabian Franz
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Backup;

use OPNsense\Core\Config;
use OPNsense\Backup\NextcloudSettings;

/**
 * Class Nextcloud backup
 * @package OPNsense\Backup
 */
class Nextcloud extends Base implements IBackupProvider
{
    /**
     * get required (user interface) fields for backup connector
     * @return array configuration fields, types and description
     */
    public function getConfigurationFields()
    {
        $fields = array(
            array(
                "name" => "enabled",
                "type" => "checkbox",
                "label" => gettext("Enable"),
                "value" => null
            ),
            array(
                "name" => "url",
                "type" => "text",
                "label" => gettext("URL"),
                "help" => gettext("The Base URL to Nextcloud without trailing slash. For example: https://cloud.example.com"),
                "value" => null
            ),
            array(
                "name" => "user",
                "type" => "text",
                "label" => gettext("User Name"),
                "help" => gettext("The name you use for logging into your Nextcloud account"),
                "value" => null
            ),
            array(
                "name" => "password",
                "type" => "password",
                "label" => gettext("Password"),
                "help" => gettext("The app password which has been generated for you"),
                "value" => null
            ),
            array(
                "name" => "password_encryption",
                "type" => "password",
                "label" => gettext("Encryption Password (Optional)"),
                "help" => gettext("A password to encrypt your configuration"),
                "value" => null
            ),
            array(
                "name" => "backupdir",
                "type" => "text",
                "label" => gettext("Directory Name without leading slash, starting from user's root"),
                "value" => 'OPNsense-Backup'
            ),
            array(
                "name" => "strategy",
                "type" => "checkbox",
                "help" => gettext("Select this one to back up to a file named config-YYYYMMDD instead of syncing contents of /conf/backup"),
                "label" => gettext("Daily file instead of sync all"),
            ),
            array(
                "name" => "addhostname",
                "type" => "checkbox",
                "label" => gettext("Backup to directory named after hostname"),
                "help" => gettext("Create subdirectory under backupdir for this host"),
                "value" => null
            ),
        );
        $nextcloud = new NextcloudSettings();
        foreach ($fields as &$field) {
            $field['value'] = (string)$nextcloud->getNodeByReference($field['name']);
        }
        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext("Nextcloud");
    }

    /**
     * validate and set configuration
     * @param array $conf configuration array
     * @return array of validation errors when not saved
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function setConfiguration($conf)
    {
        $nextCloud = new NextcloudSettings();
        $this->setModelProperties($nextCloud, $conf);
        $validation_messages = $this->validateModel($nextCloud);
        if (empty($validation_messages)) {
            $nextCloud->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * perform backup
     * @return array filelist
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function backup()
    {
        $cnf = Config::getInstance();
        $nextcloud = new NextcloudSettings();
        if ($cnf->isValid() && !empty((string)$nextcloud->enabled)) {
            $config = $cnf->object();
            $url = (string)$nextcloud->url;
            $username = (string)$nextcloud->user;
            $password = (string)$nextcloud->password;
            $backupdir = (string)$nextcloud->backupdir;
            $crypto_password = (string)$nextcloud->password_encryption;
            $strategy = (string)$nextcloud->strategy;
            // Strategy 0 = Sync /conf/backup
            // Strategy 1 = Copy /conf/config.xml to $backupdir/conf-YYYYMMDD.xml

            if (!$nextcloud->addhostname->isEmpty()) {
                $backupdir .= "/".gethostname()."/";
            }

            // Check if destination directory exists, create (full path) if not
            try {
                $internal_username = $this->getInternalUsername($url, $username, $password);
                $this->create_directory($url, $username, $password, $internal_username, $backupdir);
            } catch (\Exception $e) {
                return array();
            }

            // Backup strategy 1, sync /conf/config.xml to $backupdir/config-YYYYMMDD.xml
            if ($strategy) {
                $confdata = file_get_contents('/conf/config.xml');
                $mdate = filemtime('/conf/config.xml');
                $datestring = date('Ymd', $mdate);
                $target_filename = 'config-' . $datestring . '.xml';
                // Find the same filename @ remote
                $remote_filename = $url . '/remote.php/dav/files/' . $internal_username . '/' . $backupdir . '/' . $target_filename;
                try {
                    $reply = $this->curl_request_nothrow($remote_filename, $username, $password, 'PROPFIND', 'Cannot get remote fileinfo');
                    $http_code = $reply['info']['http_code'];
                    if ($http_code >= 200 && $http_code < 300) {
                        $xml_data = $reply['response'];
                        if ($xml_data == NULL) {
                            syslog(LOG_ERR, 'Data was NULL');
                            return array();
                        }
                        $xml_data = str_replace(['<d:', '</d:'], ['<', '</'], $xml_data);
                        $xml = simplexml_load_string($xml_data);
                        foreach ($xml->children() as $response) {
                            if ($response->getName() == 'response') {
                                $lastmodifiedstr = $response->propstat->prop->getlastmodified;
                                $filedate = strtotime($lastmodifiedstr);
                                if ($filedate >= $mdate) {
                                    syslog(LOG_ERR, 'Remote file is same or newer than local');
                                    return array();
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    syslog(LOG_ERR, $e);
                    // Exceptions are not fatal at this point
                    // log for information, but keep going
                }
                // Optionally encrypt
                if (!empty($crypto_password)) {
                    $confdata = $this->encrypt($confdata, $crypto_password);
                }
                // Finally, upload some data
                try {
                    $this->upload_file_content(
                        $url,
                        $username,
                        $password,
                        $internal_username,
                        $backupdir,
                        $target_filename,
                        $confdata
                    );
                    return array($backupdir . '/' . $target_filename);
                } catch (\Exception $e) {
                    syslog(LOG_ERR, 'Backup to ' . $url . ' failed: ' . $e);
                    return array();
                }
            }

            // Default strategy (0), sync /conf/backup/

            // Get list of files from local backup system
            $local_files = array();
            $tmp_local_files = scandir('/conf/backup/');
            // Remove '.' and '..', skip directories
            foreach ($tmp_local_files as $tmp_local_file) {
                if ($tmp_local_file === '.' || $tmp_local_file === '..') {
                    continue;
                }
                if (!is_file("/conf/backup/".$tmp_local_file)) {
                    continue;
                }
                $local_files[] = $tmp_local_file;
            }

            // Get list of filenames (without path) on remote location
            $remote_files = array();
            $tmp_remote_files = $this->listfiles($url, $username, $password, $internal_username, "/$backupdir/", false);
            foreach ($tmp_remote_files as $tmp_remote_file) {
                $remote_files[] = pathinfo($tmp_remote_file)['basename'];
            }


            $uploaded_files = array();

            // Loop over each local file,
            // see if it's in $remote_files,
            // if not, optionally encrypt, and upload
            foreach ($local_files as $file_to_upload) {
                if (!in_array($file_to_upload, $remote_files)) {
                    $confdata = file_get_contents("/conf/backup/$file_to_upload");
                    if (!empty($crypto_password)) {
                        $confdata = $this->encrypt($confdata, $crypto_password);
                    }
                    try {
                        $this->upload_file_content(
                            $url,
                            $username,
                            $password,
                            $internal_username,
                            $backupdir,
                            $file_to_upload,
                            $confdata
                        );
                        $uploaded_files[] = $file_to_upload;
                    } catch (\Exception $e) {
                        return $uploaded_files;
                    }
                }
            }
            return $uploaded_files;
        }
    }

    /**
     * dir listing
     * @param string $url remote location
     * @param string $username username
     * @param string $password password to use
     * @param string $internal_username internal username for the webdav directory
     * @param string $directory location to list
     * @param bool $only_dirs only list directories
     * @return array
     * @throws \Exception
     */
    public function listFiles($url, $username, $password, $internal_username, $directory = '/', $only_dirs = true)
    {
        $result = $this->curl_request(
            "$url/remote.php/dav/files/$internal_username$directory",
            $username,
            $password,
            'PROPFIND',
            "Error while fetching filelist from Nextcloud '{$directory}' path"
        );
        // workaround - simplexml seems to be broken when using namespaces - remove them.
        $xml = simplexml_load_string(str_replace(['<d:', '</d:'], ['<', '</'], $result['response']));
        $ret = array();
        foreach ($xml->children() as $response) {
            // d:response
            if ($response->getName() == 'response') {
                $fileurl =  (string)$response->href;
                $dirname = explode("/remote.php/dav/files/$internal_username", $fileurl, 2)[1];
                if (
                    $response->propstat->prop->resourcetype->children()->count() > 0 &&
                    $response->propstat->prop->resourcetype->children()[0]->getName() == 'collection' &&
                    $only_dirs
                ) {
                    $ret[] = $dirname;
                } elseif (!$only_dirs) {
                    $ret[] = $dirname;
                }
            }
        }
        return $ret;
    }

    /**
     * upload file
     * @param string $url remote location
     * @param string $username remote user
     * @param string $password password to use
     * @param string $backupdir remote directory
     * @param string $filename filename to use
     * @param string $local_file_content contents to save
     * @throws \Exception when upload fails
     */
    public function upload_file_content($url, $username, $password, $internal_username, $backupdir, $filename, $local_file_content)
    {
        $url = $url . "/remote.php/dav/files/$internal_username/$backupdir/$filename";
        $reply = $this->curl_request(
            $url,
            $username,
            $password,
            'PUT',
            'cannot execute PUT',
            $local_file_content
        );
        $http_code = $reply['info']['http_code'];
        // Accepted http codes for upload is 200-299
        if (!($http_code >= 200 && $http_code < 300)) {
            syslog(LOG_ERR, 'Could not PUT '. $url);
            throw new \Exception();
        }
    }

    /**
     * create new remote directory if doesn't exist
     * @param string $url remote location
     * @param string $username remote user
     * @param string $password password to use
     * @param string $backupdir remote directory
     * @throws \Exception when create dir fails
     */
    public function create_directory($url, $username, $password, $internal_username, $backupdir)
    {
        $parent_path = dirname($backupdir);
        try {
            $directories = $this->listFiles($url, $username, $password, $internal_username, "/{$parent_path}");
        } catch (\Exception $e) {
            if ($backupdir == ".") {
                // We cannot create root, if we reached here there's some other problem
                syslog(LOG_ERR, "Check Nextcloud configuration parameters");
                return false;
            }
            // If error assume dir doesn't exist. Create parent folder
            if ($this->create_directory($url, $username, $password, $internal_username, $parent_path) === false) {
                throw new \Exception();
            }
        }
        // if path exists ok
        if (in_array("/{$backupdir}/", $directories)) {
            return;
        }

        $this->curl_request(
            $url . "/remote.php/dav/files/{$internal_username}/{$backupdir}",
            $username,
            $password,
            'MKCOL',
            'cannot execute MKCOL'
        );
    }

    public function getInternalUsername($url, $username, $password): string
    {
        try {
            $xml_response = $this->ocs_request(
                "$url/ocs/v1.php/cloud/user",
                $username,
                $password,
                "GET",
                "Cannot get real username"
            );
            $data = $xml_response->data;
            if ($data == null) {
                return $username; // no data found, return the old username
            }
            $real_username = $data->id;
            if ($real_username == null) {
                return $username;
            }
            return $real_username;
        } catch (\Exception $exception) {
            return $username; // error - continue with old username
        }
    }

    /**
     * @param string $url remote location
     * @param string $username remote user
     * @param string $password password to use
     * @param string $method http method, PUT, GET, ...
     * @param string $error_message message to log on failure
     * @param null|string $postdata http body
     * @param array $headers HTTP headers
     * @return array response status
     * @throws \Exception when request fails
     */
    public function curl_request(
        $url,
        $username,
        $password,
        $method,
        $error_message,
        $postdata = null,
        $headers = array("User-Agent: OPNsense Firewall")
    ) {
        $result = $this->curl_request_nothrow($url, $username, $password, $method, $error_message, $postdata, $headers);
        $info = $result['info'];
        $err = $result['err'];
        $response = $result['response'];
        if (!($info['http_code'] == 200 || $info['http_code'] == 207 || $info['http_code'] == 201) || $err) {
            syslog(LOG_ERR, $error_message);
            syslog(LOG_ERR, json_encode($info));
            throw new \Exception();
        }
        return array('response' => $response, 'info' => $info);
    }


    // Add this here, since I'm fundamentally opposed to throwing exceptions
    // if http codes aren't to your liking in a generic function.
    // Delegate that to upper functions, where it belongs.
    public function curl_request_nothrow(
        $url,
        $username,
        $password,
        $method,
        $error_message,
        $postdata = null,
        $headers = array("User-Agent: OPNsense Firewall")
    ) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method, // Create a file in WebDAV is PUT
            CURLOPT_RETURNTRANSFER => true, // Do not output the data to STDOUT
            CURLOPT_VERBOSE => 0,           // same here
            CURLOPT_MAXREDIRS => 0,         // no redirects
            CURLOPT_TIMEOUT => 60,          // maximum time: 1 min
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERPWD => $username . ":" . $password,
            CURLOPT_HTTPHEADER => $headers
        ));
        if ($postdata != null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        }
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        return array('response' => $response, 'info' => $info, 'err' => $err);
    }

    /**
     * @param $url string URL to call
     * @param $username string username
     * @param $password string password
     * @param $method string HTTP verb
     * @param $error_message string error message to forward to the http calling method
     * @param null $postdata post data if any (can be null)
     * @return array|\SimpleXMLElement|null
     * @throws \Exception
     */
    public function ocs_request($url, $username, $password, $method, $error_message, $postdata = null)
    {
        $headers = $headers = array("User-Agent: OPNsense Firewall", "OCS-APIRequest: true");
        $result = $this->curl_request($url, $username, $password, $method, $error_message, $postdata, $headers);
        if (array_key_exists('content_type', $result['info'])) {
            if (stripos($result['info']['content_type'], 'xml') !== false) {
                return new \SimpleXMLElement($result['response']);
            }
            if (stripos($result['info']['content_type'], 'json') !== false) {
                return json_decode($result['response'], true);
            }

            throw new \Exception();
        }

        return null;
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function isEnabled()
    {
        $nextCloud = new NextcloudSettings();
        return (string)$nextCloud->enabled === "1";
    }
}
