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
            array(
                "name" => "numdays",
                "type" => "text",
                "label" => gettext("Number of days worth of backups to keep"),
                "help" => gettext("This works in collaboration with Number of backups below, the one with the oldest/most will win"),
                "value" => null
            ),
            array(
                "name" => "numbackups",
                "type" => "text",
                "label" => gettext("Number of backups to keep"),
                  "help" => gettext("This works in collaboration with Number of days above, the one with the oldest/most will win"),
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
     * check remote file last modified tag
     * @param string $remote_filename filename to check on server
     * @param string $username username for login to server
     * @param string $password password for authentication
     * @return int unix timestamp or 0 if errors occour
     */
    public function get_remote_file_lastmodified(
        $remote_filename,
        $username,
        $password
    ) {
        $reply = $this->curl_request_nothrow($remote_filename, $username, $password, 'PROPFIND', 'Cannot get remote fileinfo');
        $http_code = $reply['info']['http_code'];
        if ($http_code >= 200 && $http_code < 300) {
            $xml_data = $reply['response'];
            if ($xml_data == NULL) {
                syslog(LOG_ERR, 'Data was NULL');
                return 0;
            }
            $xml_data = str_replace(['<d:', '</d:'], ['<', '</'], $xml_data);
            $xml = simplexml_load_string($xml_data);
            foreach ($xml->children() as $response) {
                if ($response->getName() == 'response') {
                    $lastmodifiedstr = $response->propstat->prop->getlastmodified;
                    $filedate = strtotime($lastmodifiedstr);
                    return $filedate;
                }
            }
        }
        return 0;
    }

    /**
     * backup with strategy 0 (copy everything in /conf/backup/ to $backupdir/)
     * @param string $internal_username the returnvalue from $this->getInternalUsername
     * @param string $username
     * @param string $password
     * @param string $url server protocol and hostname
     * @param string $backupdir
     * @param string $crypto_password
     */
    public function backupstrat_zero(
        $internal_username,
        $username,
        $password,
        $url,
        $backupdir,
        $crypto_password
    ) {
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

    /**
     * backup with strategy 1 (copy /conf/config.xml to $backupdir/conf-YYYYMMDD.xml)
     * @param string $internal_username the returnvalue from $this->getInternalUsername
     * @param string $username
     * @param string $password
     * @param string url server protocol and hostname
     * @param string $backupdir
     * @param string $crypto_password
    */
    public function backupstrat_one(
        $internal_username,
        $username,
        $password,
        $url,
        $backupdir,
        $crypto_password
    ) {
        $confdata = file_get_contents('/conf/config.xml');
        $mdate = filemtime('/conf/config.xml');
        $datestring = date('Ymd', $mdate);
        $target_filename = 'config-' . $datestring . '.xml';
        // Find the same filename @ remote
        $remote_filename = $url . '/remote.php/dav/files/' . $internal_username . '/' . $backupdir . '/' . $target_filename;
        $remote_file_date = $this->get_remote_file_lastmodified($remote_filename, $username, $password);
        if ($remote_file_date >= $mdate) {
            return array();
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

    /**
     * Get timestamp value from filename in list
     * @param $filelist array of files in remote location
     * @return array($filedata => $filename)
     */
    public function get_filelist_dates($filelist) {
        // Save as associative array
        // key = lastmodified
        // value = filename
        $files = array();
        foreach ($filelist as $target_filename) {
            // Find suggested creation date
            // Base this on the filename. Either it is a unix timestamp, or it should be YYYYMMDD
            // Either way, it's the part between "config-" and ".xml"
            $filestr_no_xml = explode(".xml", $target_filename)[0];
            $filedatestr = intval(explode("-", $filestr_no_xml)[1]);
            if (($filedate = strtotime($filedatestr)) === false) {
                // Cannot convert string to time.. probably already a unix-timestamp
                // Try to convert with date()
                $date = date(DATE_ATOM, $filedatestr);
                // Then to a UNIX timestamp again
                $maybedate = strtotime($date);
                if ($maybedate === $filedatestr) {
                    // They represent the same time, this is good
                    // Just copy the intval() and be done with this
                    $filedate = $filedatestr;
                }
            }
            if ($filedate) {
                $files[(string)$filedate] = $target_filename;
            } else {
                syslog(LOG_ERR, "Skipping file " . $target_filename . ", cannot determine date");
            }
        }
        ksort($files);
        return $files;
    }

    /**
     * housekeeping
     * @param $internal_username returnvalue from $this->getInternalUsername
     * @param $username
     * @param $password
     * @param $url protocol and hostname of server
     * @param $backupdir directory to operate in
     * @param $keep_days number of days to keep backups for
     * @param $keep_num number of backups to keep
     */
    public function retention(
        $internal_username,
        $username,
        $password,
        $url,
        $backupdir,
        $keep_days,
        $keep_num
    ) {
        // Get list of filenames (without path) on remote location
        $remote_files = array();
        $tmp_remote_files = $this->listfiles($url, $username, $password, $internal_username, "/$backupdir/", false);
        foreach ($tmp_remote_files as $tmp_remote_file) {
            if (!($tmp_remote_file === "")) {
                if (!($tmp_remote_file == "/$backupdir/")) {
                    // No idea why the root directory is in the list..
                    $remote_files[] = pathinfo($tmp_remote_file)['basename'];
                }
            }
        }
        $num_remote_files = count($remote_files);
        // Short-circuit, if too few files, no need to check no more
        if (!($keep_num === "")) {
            if ($keep_num > $num_remote_files) {
                return;
            }
        }

        $date = new \DateTime();
        $files = $this->get_filelist_dates($remote_files);
        if (strlen($keep_days)) {
            // Admin has specified number of days to keep
            $dateinterval = \DateInterval::createFromDateString($keep_days . " day");
            $target_timestamp = date_sub($date, $dateinterval)->format('U');
            // $files is an associative array with key=creation_time, value=filename
            // should be sorted by ksort, hopefully that is a numerical sort:)
            $new_files = array();
            $old_files = array();
            foreach(array_keys($files) as $file_timestamp) {
                if ($file_timestamp > $target_timestamp) {
                    $new_files[(string)$file_timestamp] = $files[$file_timestamp];
                } else {
                    // file is "old", aka ripe for deletion
                    $old_files[(string)$file_timestamp] = $files[$file_timestamp];
                }
            }
            if (strlen($keep_num)) {
                $num_new_files = count($new_files);
                if ($num_new_files < $keep_num) {
                    // Not enough new files to satisfy $keep_num
                    $missing_num = $keep_num - $num_new_files;
                    // Can we slice some files from the $old_files list to satisfy $keep_num?
                    $total_files = count($files);
                    if ($total_files >= $keep_num) {
                        // Yes, we can
                        $tmp_files = array_slice($old_files, 0, $missing_num * -1);
                        foreach(array_keys($tmp_files) as $filetodelete) {
                            $this->delete_file($url, $username, $password, $internal_username, $backupdir, $tmp_files[$filetodelete]);
                        }
                    }
                    // No, we can't. Keep all things as is
                } else {
                    // We have more new files than what we need to satisfy $keep_num
                    foreach(array_keys($old_files) as $filetodelete) {
                        $this->delete_file($url, $username, $password, $internal_username, $backupdir, $old_files[$filetodelete]);
                    }
                    // We do not delete files from new_files, as they are covered by the $keep_days
                }
            } else {
                // We have not been told to keep N items,
                // delete everything in $old_files
                foreach(array_keys($old_files) as $filetodelete) {
                    $this->delete_file($url, $username, $password, $internal_username, $backupdir, $old_files[$filetodelete]);
                }
            }
        } else {
            // No $keep_days specified
            if (strlen($keep_num)) {
                // keep_num is some number
                // Delete filenames based on their creation time
                if (count($files) > $keep_num) {
                    $tmp_files = array_slice($files, 0, $keep_num * -1);
                    foreach(array_keys($tmp_files) as $filetodelete) {
                        $this->delete_file($url, $username, $password, $internal_username, $backupdir, $tmp_files[$filetodelete]);
                    }
                }
            }
        }
    }

    /**
     * perform backup
     * @return array filelist
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function backup()
    {
        date_default_timezone_set('UTC');
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
            $keep_days = (string)$nextcloud->numdays;
            $keep_num = (string)$nextcloud->numbackups;

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

            if ($strategy) {
                $list_of_files = $this->backupstrat_one($internal_username, $username, $password, $url, $backupdir, $crypto_password);
            } else {
                $list_of_files = $this->backupstrat_zero($internal_username, $username, $password, $url, $backupdir, $crypto_password);
            }
            // Retention here
            if (!($keep_days === "") or !($keep_num === "")) {
                $this->retention($internal_username, $username, $password, $url, $backupdir, $keep_days, $keep_num);
            }
            return $list_of_files;
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
     * @param string $internal_username UUID
     * @param string $backupdir remote directory
     * @param string $filename filename to use
     * @param string $local_file_content contents to save
     * @throws \Exception when upload fails
     */
    public function upload_file_content($url, $username, $password, $internal_username, $backupdir, $filename, $local_file_content)
    {
        $url = $url . "/remote.php/dav/files/$internal_username/$backupdir/$filename";
        $reply = $this->curl_request_nothrow(
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
     * delete a file
     * @param string $url remote location
     * @param string $username remote user
     * @param string $password password to use
     * @param strign $internal_username UUID
     * @param string $backupdir remote directory
     * @param string $filename filename to use
     */
    public function delete_file($url, $username, $password, $internal_username, $backupdir, $filename) {
        $url = $url . "/remote.php/dav/files/$internal_username/$backupdir/$filename";
        $reply = $this->curl_request_nothrow(
            $url,
            $username,
            $password,
            'DELETE',
            'cannot delete file'
        );
        $http_code = $reply['info']['http_code'];
        syslog(LOG_ERR, "Deleting " . $url . " returned " . $http_code);
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
