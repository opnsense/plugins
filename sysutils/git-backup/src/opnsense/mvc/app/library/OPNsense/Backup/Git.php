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
             "name" => "privkey",
             "type" => "textarea",
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
     * @inheritdoc
     */
    public function backup()
    {
        return ['config.xml'];
    }

    /**
     * @inheritdoc
     */
    public function isEnabled()
    {
        return (string)(new GitSettings())->enabled === "1";
    }
}
