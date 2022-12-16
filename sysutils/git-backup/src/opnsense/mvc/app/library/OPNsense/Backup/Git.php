<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Backup;

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Backup\GitSettings;

/**
 * Class Git backup
 * @package OPNsense\Backup
 */
class Git extends Base implements IBackupProvider
{
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
             "help" => gettext("Target location, which defined transport protocol, such as ssh://server/project.git or https://server/project.git."),
             "value" => null
           ],
           [
             "name" => "branch",
             "type" => "text",
             "label" => gettext("Branch"),
             "help" => gettext("Target branch to push to."),
             "value" => null
           ],
           [
             "name" => "privkey",
             "type" => "passwordarea",
             "label" => gettext("SSH private key"),
             "help" => gettext("When provided, ssh based authentication will be used."),
             "value" => null
           ],
           [
             "name" => "user",
             "type" => "text",
             "label" => gettext("User Name"),
             "value" => null
           ],
           [
             "name" => "password",
             "type" => "password",
             "label" => gettext("Password"),
             "value" => null
           ]
        ];
        $mdl = new GitSettings();
        foreach ($fields as &$field) {
            $field['value'] = (string)$mdl->getNodeByReference($field['name']);
        }
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return gettext("Git");
    }

    /**
     * @inheritdoc
     */
    public function setConfiguration($conf)
    {
        $mdl = new GitSettings();
        $this->setModelProperties($mdl, $conf);
        $validation_messages = $this->validateModel($mdl);
        if (empty($validation_messages)) {
            $mdl->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * Backup is responsible for initialising the local repo and pusing it to upstream.
     * To ensure initial content, we should trigger a 'system event config_changed' which should enforce a
     * add + commit in our (newly created) repo.
     *
     * Since our backup method is also called from the userinterface directly, we should try to prevent the need
     * for elevated rights. Since all actions are concentrated within the config directory, we only need read/exec
     * access on git. (detaching this further would deviate the implementation from the existing ones)
     *
     * @return array filelist
     */
    public function backup()
    {
        $targetdir = "/conf/backup/git";
        $git = "/usr/local/bin/git";
        $mdl = new GitSettings();
        if (!is_dir($targetdir)) {
            mkdir($targetdir);
        }
        if (!is_dir('{$targetdir}/.git')) {
            exec("{$git} init {$targetdir}");
        }
        // XXX: since our git backup is plain text and already contains the private key, it doesn't really matter
        //      to keep the same key in the git directory (we're not going to push it)
        $ident_file = "{$targetdir}/identity";
        $privkey = trim(str_replace("\r", "", (string)$mdl->privkey)) . "\n";
        file_put_contents($ident_file, $privkey);
        chmod("{$targetdir}/identity", 0600);
        // When there are unprocessed config backups, flush them out.
        (new Backend())->configdRun("system event config_changed");
        // configure upstream
        exec("cd {$targetdir} && " .
            "{$git} config core.sshCommand " .
            "\"ssh -i {$ident_file} -o StrictHostKeyChecking=accept-new -o PasswordAuthentication=no\"");
        $url = (string)$mdl->url;
        $pos = strpos($url, '//');
        // inject credentials in url (either username or username:password, depending on transport)
        if (stripos(trim((string)$mdl->url), 'http') === 0) {
            $cred = urlencode((string)$mdl->user) . ":" . urlencode((string)$mdl->password);
            $url = substr($url, 0, $pos + 2) . "{$cred}@" . substr($url, $pos + 2);
        } else {
            $url = substr($url, 0, $pos + 2) . urlencode((string)$mdl->user) . "@" . substr($url, $pos + 2);
        }
        exec("cd {$targetdir} && {$git} remote remove origin");
        exec("cd {$targetdir} && {$git} remote add origin " . escapeshellarg($url));
        $pushtxt = shell_exec(
            "(cd {$targetdir} && {$git} push origin " . escapeshellarg("master:{$mdl->branch}") .
            " && echo '__exit_ok__') 2>&1"
        );
        if (strpos($pushtxt, '__exit_ok__')) {
            $error_type = null;
        } elseif (strpos($pushtxt, 'Permission denied') || strpos($pushtxt, 'Authentication failed ')) {
            $error_type = "authentication failure";
        } elseif (strpos($pushtxt, 'WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED')) {
            $error_type = "ssh hostkey changed";
        } elseif (strpos($pushtxt, "remote contains work that you do")) {
            $error_type = "git out of sync";
        } else {
            $error_type = "unknown error, check log for details";
        }
        if (!empty($error_type)) {
            syslog(LOG_ERR, "git-backup {$error_type} (" . str_replace("\n", " ", $pushtxt) . ")");
            throw new \Exception($error_type);
        } else {
            // return filelist in git
            return explode("\n", shell_exec("cd {$targetdir} && git ls-files"));
        }
    }

    /**
     * @inheritdoc
     */
    public function isEnabled()
    {
        return (string)(new GitSettings())->enabled === "1";
    }
}
