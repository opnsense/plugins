<?php

/**
 *    Copyright (C) 2018 EURO-LOG AG
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

namespace OPNsense\Relayd;

use OPNsense\Base\BaseModel;

/**
 * Class Relayd
 * @package OPNsense\Relayd
 */
class Relayd extends BaseModel
{
    /**
     * get configuration state
     * @return bool
     */
    public function configChanged()
    {
        return file_exists("/tmp/relayd.dirty");
    }

    /**
     * mark configuration as changed
     * @return bool
     */
    public function configDirty()
    {
        return @touch("/tmp/relayd.dirty");
    }

    /**
     * mark configuration as consistent with the running config
     * @return bool
     */
    public function configClean()
    {
        return @unlink("/tmp/relayd.dirty");
    }

    /**
     * @param string $type type of object (host, table, virtualserver)
     * @param string $name name of the attribute
     * @param string $value value to match
     * @return ArrayField[] items found
     */
    public function getObjectsByAttribute($type, $name, $value)
    {
        $results = [];
        foreach ($this->$type->iterateItems() as $item) {
            if ((string)$item->$name == $value) {
                  $results[] = $item;
            }
        }
        return $results;
    }
}
