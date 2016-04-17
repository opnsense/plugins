<?php
/**
 *    Copyright (C) 2016 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\HAProxy;

use OPNsense\Base\BaseModel;

/**
 * Class HAProxy
 * @package OPNsense\HAProxy
 */
class HAProxy extends BaseModel
{
    /**
     * retrieve frontend by number
     * @param $frontendid frontend number
     * @return null|BaseField frontend details
     */
    public function getByFrontendID($frontendid)
    {
        foreach ($this->frontends->frontend->__items as $frontend) {
            if ((string)$frontendid === (string)$frontend->frontendid) {
                return $frontend;
            }
        }
        return null;
    }

    /**
     * retrieve backend by number
     * @param $backendid frontend number
     * @return null|BaseField backend details
     */
    public function getByBackendID($backendid)
    {
        foreach ($this->backends->backend->__items as $backend) {
            if ((string)$backendid === (string)$backend->backendid) {
                return $backend;
            }
        }
        return null;
    }

    /**
     * check if module is enabled
     * @return bool is the HAProxy enabled (1 or more active frontends)
     */
    public function isEnabled()
    {
        foreach ($this->frontends->frontend->__items as $frontend) {
            if ((string)$frontend->enabled == "1") {
                return true;
            }
        }
        return false;
    }
}
