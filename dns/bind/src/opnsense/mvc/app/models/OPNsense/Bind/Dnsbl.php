<?php

/*
    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Bind;

use OPNsense\Base\BaseModel;

class Dnsbl extends BaseModel
{
    private static $optionValues = null;

    public static function getOptionValues()
    {
        $unboundXml = '/usr/local/opnsense/mvc/app/models/OPNsense/Unbound/Unbound.xml';
        if (self::$optionValues !== null) {
            return self::$optionValues;
        }

        self::$optionValues = [];
        if (file_exists($unboundXml)) {
            $xml = simplexml_load_file($unboundXml);
            if ($xml !== false) {
                foreach ($xml->xpath('//dnsbl/blocklist/type/OptionValues/*') as $opt) {
                    $group = [];
                    foreach ($opt->children() as $child) {
                        $group[$child->getName()] = trim((string)$child);
                    }
                    if (!empty($group)) {
                        self::$optionValues[(string)$opt['value']] = $group;
                    }
                }
            }
        }

        return self::$optionValues;
    }

    protected function init()
    {
        if ($this->type && !empty(self::getOptionValues())) {
            $this->type->setOptionValues(self::getOptionValues());
        }
    }
}
