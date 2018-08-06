<?php

/*
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace VerbNetworks\ConfigSync;

use \OPNsense\Core\Config;

class ControllerUtils
{
    
    public static function getHostid()
    {
        $hostid = '00000000-0000-0000-0000-000000000000';
        if (file_exists('/etc/hostid')) {
            $hostid = trim(file_get_contents('/etc/hostid'));
        }
        return $hostid;
    }
    
    public static function getHostname()
    {
        $cnf = Config::getInstance();
        return strtolower($cnf->object()->system->hostname);
    }
    
    public static function packData($data)
    {
        return base64_encode(gzcompress(json_encode($data), 9));
    }
    
    public static function unpackData($data)
    {
        json_decode(gzuncompress(base64_decode($data)));
    }
    
    public static function unpackValidationMessages($model, $namespace)
    {
        $response = array();
        $validation_messages = $model->performValidation();
        foreach ($validation_messages as $field => $message) {
            $response[$namespace.'.'.$message->getField()] = $message->getMessage();
        }
        return $response;
    }
}
