<?php

/*
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Fabian Franz
 * Copyright (C) 2023 Nils Stein
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
use OPNsense\Backup\WebdavSettings;

/**
 * Class Webdav backup
 * @package OPNsense\Backup
 */
class Webdav extends Base implements IBackupProvider
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
                "help" => gettext("The URL to the WebDAV Server without trailing slash. For example: https://dav.example.com/servlet/webdav.infostore"),
                "value" => null
            ),
            array(
                "name" => "user",
                "type" => "text",
                "label" => gettext("User Name"),
                "help" => gettext("The name you use for logging into your WebDAV account"),
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
                "label" => gettext("Directory Name without leading slash"),
                "value" => 'OPNsense-Backup'
            )
        );
        $webdav = new WebdavSettings();
        foreach ($fields as &$field) {
            $field['value'] = (string)$webdav->getNodeByReference($field['name']);
        }
        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext("Webdav");
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
        $webDav = new WebdavSettings();
        $this->setModelProperties($webDav, $conf);
        $validation_messages = $this->validateModel($webDav);
        if (empty($validation_messages)) {
            $webDav->serializeToConfig();
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
        $webdav = new WebdavSettings();
        if ($cnf->isValid() && !empty((string)$webdav->enabled)) {
            $config = $cnf->object();
            $url = (string)$webdav->url;
            $username = (string)$webdav->user;
            $password = (string)$webdav->password;
            $backupdir = (string)$webdav->backupdir;
            $crypto_password = (string)$webdav->password_encryption;
            $hostname = $config->system->hostname . '.' . $config->system->domain;
            $configname = 'config-' . $hostname . '-' .  date('Y-m-d_H_i_s') . '.xml';
            // backup source data to local strings (plain/encrypted)
            $confdata = file_get_contents('/conf/config.xml');
            if (!empty($crypto_password)) {
                $confdata = $this->encrypt($confdata, $crypto_password);
            }
            // Check if destination directory exists, create (full path) if not
            try {
                $this->create_directory($url, $username, $password, $backupdir);
            } catch (\Exception $e) {
                return array();
            }

            try {
                $this->upload_file_content(
                    $url,
                    $username,
                    $password,
                    $backupdir,
                    $configname,
                    $confdata
                );
                // do not list directories
                return array_filter(
                    $this->listFiles($url, $username, $password, "/$backupdir/", false),
                    function ($filename) {
                        return (substr($filename, -1) !== '/');
                    }
                );
            } catch (\Exception $e) {
                return array();
            }
        }
    }

    /**
     * dir listing
     * @param string $url remote location
     * @param string $username username
     * @param string $password password to use
     * @param string $directory location to list
     * @param bool $only_dirs only list directories
     * @return array
     * @throws \Exception
     */
    public function listFiles($url, $username, $password, $directory = '/', $only_dirs = true)
    {
        $headers = array("User-Agent: OPNsense Firewall", "Depth: 1");
        $result = $this->curl_request(
            "$url$directory",
            $username,
            $password,
            'PROPFIND',
            "Error while fetching filelist from Webdav '{$directory}' path",
            null,
            $headers
        );
        // workaround - simplexml seems to be broken when using namespaces - remove them.
        $xml = simplexml_load_string(str_ireplace(['<d:', '</d:'], ['<', '</'], $result['response']));
        $ret = array();
        foreach ($xml->children() as $response) {
            // d:response
            if ($response->getName() == 'response') {
                $fileurl =  (string)$response->href;
                $dirname = end(array_filter(explode("/",$fileurl)));
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
    public function upload_file_content($url, $username, $password, $backupdir, $filename, $local_file_content)
    {
        $this->curl_request(
            $url . "/$backupdir/$filename",
            $username,
            $password,
            'PUT',
            'cannot execute PUT',
            $local_file_content
        );
    }

    /**
     * create new remote directory if doesn't exist
     * @param string $url remote location
     * @param string $username remote user
     * @param string $password password to use
     * @param string $backupdir remote directory
     * @throws \Exception when create dir fails
     */
    public function create_directory($url, $username, $password, $backupdir)
    {
        $parent_path = dirname($backupdir);
        try {
            $directories = $this->listFiles($url, $username, $password, "/{$parent_path}");
        } catch (\Exception $e) {
            if ($backupdir == ".") {
                // We cannot create root, if we reached here there's some other problem
                syslog(LOG_ERR, "Check Webdav configuration parameters");
                return false;
            }
            // If error assume dir doesn't exist. Create parent folder
            if ($this->create_directory($url, $username, $password, $parent_path) === false) {
                throw new \Exception();
            }
        }
        // if path exists ok
        if (in_array("$backupdir", $directories)) {
            return;
        }

        $this->curl_request(
            $url . "/{$backupdir}",
            $username,
            $password,
            'MKCOL',
            'cannot execute MKCOL'
        );
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
        if (!($info['http_code'] == 200 || $info['http_code'] == 207 || $info['http_code'] == 201) || $err) {
            syslog(LOG_ERR, $error_message);
            syslog(LOG_ERR, json_encode($info));
            throw new \Exception();
        }
        curl_close($curl);
        return array('response' => $response, 'info' => $info);
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function isEnabled()
    {
        $webDav = new WebdavSettings();
        return (string)$webDav->enabled === "1";
    }
}
