<?php

/*
 * Copyright (C) 2021 Manuel Hofmann
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
use OPNsense\Backup\FTPSettings;

/**
 * Class FTP backup
 * @package OPNsense\Backup
 */
class FTP extends Base implements IBackupProvider
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
                "help" => gettext("The URL to server with trailing slash. For example: ftp://ftp.example.com/ or ftp://ftp.example.com/folder/"),
                "value" => null
            ),
            array(
                "name" => "user",
                "type" => "text",
                "label" => gettext("User Name"),
                "help" => gettext("The name you use for logging into your FTP server"),
                "value" => null
            ),
            array(
                "name" => "password",
                "type" => "password",
                "label" => gettext("Password"),
                "help" => gettext("The password for your FTP user"),
                "value" => null
            ),
            array(
                "name" => "password_encryption",
                "type" => "password",
                "label" => gettext("Encryption Password (Optional)"),
                "help" => gettext("A password to encrypt your configuration"),
                "value" => null
            )
        );
        $ftp = new FTPSettings();
        foreach ($fields as &$field) {
            $field['value'] = (string)$ftp->getNodeByReference($field['name']);
        }
        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext("FTP");
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
        $ftp = new FTPSettings();
        $this->setModelProperties($ftp, $conf);
        $validation_messages = $this->validateModel($ftp);
        if (empty($validation_messages)) {
            $ftp->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * perform backup
     * @return array filelist
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
	 * @throws \Exception
     */
    public function backup()
    {
        $cnf = Config::getInstance();
        $ftp = new FTPSettings();
        
        if ($cnf->isValid() && !empty((string)$ftp->enabled)) {
            $config = $cnf->object();

            $url = (string)$ftp->url;
            $user = (string)$ftp->user;
            $password = (string)$ftp->password;
            $crypto_password = (string)$ftp->password_encryption;
			$hostname = $config->system->hostname . '.' . $config->system->domain;
            
            $configname = 'config-' . $hostname . '-' .  date('Y-m-d_H_i_s') . '.xml';
            
            $confdata = file_get_contents('/conf/config.xml');
            if (!empty($crypto_password)) {
                $confdata = $this->encrypt($confdata, $crypto_password);
            }
            
			$this->upload_file_content(
				$url,
				$user,
				$password,
				$configname,
				$confdata );
			
			return $this->listFiles($url, $user, $password);
        }

    }

    /**
     * dir listing
     * @param string $url remote location
     * @param string $username username
     * @param string $password password to use
     * @return array
     * @throws \Exception when listing files fails
     */
    public function listFiles($url, $username, $password)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true, // Do not output the data to STDOUT
            CURLOPT_TIMEOUT => 60,          // maximum time: 1 min
            CURLOPT_USERPWD => $username . ":" . $password,
            CURLOPT_DIRLISTONLY => 1,
        ));
        
        $response = curl_exec($curl);
        
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if($error){
            syslog(LOG_ERR, $error);
            throw new \Exception($error);
        }

		$files = explode("\n", $response);
		
		// e.g. filter folders ".." and "."
        return preg_grep('"config-.*\.xml"', $files);
    }

    /**
     * upload file
     * @param string $url remote location
     * @param string $username remote user
     * @param string $password password to use
     * @param string $filename filename to use
     * @param string $file_content contents to save
     * @throws \Exception when upload fails
     */
    public function upload_file_content($url, $username, $password, $filename, $file_content)
    {
        $curl = curl_init();
    
        $infile = tmpfile();
        fwrite($infile, $file_content); 
        fseek($infile, 0); 
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url . $filename,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_UPLOAD => 1,
            CURLOPT_INFILE => $infile,
            CURLOPT_INFILESIZE => strlen($file_content),
            CURLOPT_USERPWD => $username . ":" . $password,
        ));

        curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);
        
        if($error){
            syslog(LOG_ERR, $error);
            throw new \Exception($error);
        }
        
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function isEnabled()
    {
        $ftp = new FTPSettings();
        return (string)$ftp->enabled === "1";
    }
}
