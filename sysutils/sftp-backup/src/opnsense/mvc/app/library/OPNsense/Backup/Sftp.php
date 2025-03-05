<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\File;
use OPNsense\Backup\SftpSettings;

/**
 * Class Sftp backup
 * @package OPNsense\Backup
 */
class Sftp extends Base implements IBackupProvider
{
    private $model = null;

    public function __construct()
    {
        $this->model = new SftpSettings();
    }

    /**
     * @inheritdoc
     */
    public function getConfigurationFields()
    {
        $fields = [
           [
                "name" => "enabled",
                "type" => "checkbox",
                "label" => gettext("Enable"),
                "value" => null
           ],
           [
                "name" => "url",
                "type" => "text",
                "label" => gettext("URL"),
                "help" => gettext(
                    "Target location, specified as uri, e.g. sftp://user@my.host.at.domain[:port]//path/to/backup"
                ),
                "value" => null
           ],
           [
                "name" => "privkey",
                "type" => "passwordarea",
                "label" => gettext("SSH private key"),
                "help" => gettext("The private key used to setup the connection."),
                "value" => null
           ],
           [
                "name" => "backupcount",
                "type" => "text",
                "label" => gettext("Backup Count"),
                "value" => null
           ],
           [
                "name" => "password",
                "type" => "password",
                "label" => gettext("Encrypt Password"),
                "value" => null
           ],
           [
                "name" => "passwordconfirm",
                "type" => "password",
                "label" => gettext("Confirm"),
                "value" => null
           ]
        ];
        foreach ($fields as &$field) {
            if ($field['name'] == 'passwordconfirm') {
                $field['value'] = (string)$this->model->getNodeByReference('password');
            } else {
                $field['value'] = (string)$this->model->getNodeByReference($field['name']);
            }
        }
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return gettext("sftp");
    }

    /**
     * @inheritdoc
     */
    public function setConfiguration($conf)
    {
        $this->setModelProperties($this->model, $conf);
        $validation_messages = $this->validateModel($this->model);
        if ($conf['passwordconfirm'] != $conf['password']) {
            $validation_messages[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }
        if (empty($validation_messages)) {
            $this->model->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * sftp command
     * @param string $sftpcmd command to execute
     * @return array [stdout|stderr|exit_status]
     */
    private function sftpCmd($sftpcmd)
    {
        $cmd = [
            '/usr/local/bin/sftp',
            '-o StrictHostKeyChecking=accept-new',
            '-o PasswordAuthentication=no',
            '-o ChallengeResponseAuthentication=no',
            '-i ' . $this->getIdentity(),
            escapeshellarg($this->model->url)
        ];

        $result = ['exit_status' => -1, 'stderr' => '', 'stdout' => ''];
        $process = proc_open(
            implode(' ', $cmd),
            [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]],
            $pipes
        );
        if (is_resource($process)) {
            fwrite($pipes[0], $sftpcmd);
            fclose($pipes[0]);
            $result['stdout'] = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $result['stderr'] = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $result['exit_status'] = proc_close($process);
        }
        if ($result['exit_status'] !== 0) {
            /* always throw on non zero exit status */
            syslog(LOG_ERR, "sftp-backup error (" . str_replace("\n", " ", $result['stderr']) . ")");
            throw new \Exception($result['stderr']);
        }
        return $result;
    }

    /**
     * @return identity file, create new when non existent
     */
    private function getIdentity()
    {
        $confdir = "/conf/backup/sftp";
        $identfile = $confdir . '/identity';
        if (!is_dir($confdir)) {
            mkdir($confdir);
        }
        if (!is_file($identfile) || file_get_contents($identfile) != $this->model->privkey) {
            File::file_put_contents($identfile, $this->model->privkey, 0600);
        }
        return $identfile;
    }

    /**
     * @return list of files on remote location
     */
    private function ls($pattern='')
    {
        $result = [];
        foreach (explode("\n", $this->sftpCmd('ls -lnt '. $pattern)['stdout']) as $line) {
            $parts = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) >= 7) {
                $result[] = $parts[count($parts)-1];
            }
        }
        return $result;
    }

    /**
     * @param string $source filename
     * @param string $destination filename
     */
    private function put($source, $destination)
    {
        $this->sftpCmd(sprintf('put %s %s', $source, $destination));
    }

    /**
     * @param string $filename
     */
    private function del($filename)
    {
        $this->sftpCmd(sprintf('rm %s', $filename));
    }

    /**
     * @return array filelist
     */
    public function backup()
    {
        if ($this->model->enabled->isEmpty()) {
            /* disabled */
            return;
        }
        /**
         * Collect most recent backup, since /conf/backup/ always contains the latests, we can use the filename
         * for easy comparison.
         **/
        $all_backups = glob('/conf/backup/config-*.xml');
        $most_recent = $all_backups[count($all_backups) - 1];
        $confdata = file_get_contents($most_recent);
        if (!$this->model->password->isEmpty()) {
            $confdata = $this->encrypt($confdata, (string)$this->model->password);
        }
        /* backup filename when not already on remote location */
        $remote_backups = $this->ls('config-*.xml');
        $target_filename = basename($most_recent);
        if (!in_array($target_filename, $remote_backups)) {
            syslog(LOG_NOTICE, "backup configuration as " . $target_filename);
            $tmpfilename = sprintf("/conf/backup/sftp/%s", $target_filename);
            File::file_put_contents($tmpfilename, $confdata, 0600);
            $this->put($tmpfilename, $target_filename);
            unlink($tmpfilename);
            $remote_backups = $this->ls('config-*.xml');
        }
        /* cleanup */
        rsort($remote_backups);
        if (count($remote_backups) > (int)$this->model->backupcount->getCurrentValue()) {
            for ($i = $this->model->backupcount->getCurrentValue() ; $i < count($remote_backups); $i++) {
                $this->del($remote_backups[$i]);
            }
            $remote_backups = $this->ls('config-*.xml');
        }

        return $remote_backups;
    }

    /**
     * @inheritdoc
     */
    public function isEnabled()
    {
        return !$this->model->enabled->isEmpty();
    }
}
