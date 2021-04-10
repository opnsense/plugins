<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
 * Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Freeradius\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class LeaseController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'lease';
    protected static $internalModelClass = '\OPNsense\Freeradius\Lease';

    public function searchLeaseAction()
    {
        return $this->searchBase('leases.lease', array("enabled", "mac", "ip"));
    }
    public function getLeaseAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('lease', 'leases.lease', $uuid);
    }
    public function addLeaseAction()
    {
        return $this->addBase('lease', 'leases.lease');
    }
    public function delLeaseAction($uuid)
    {
        return $this->delBase('leases.lease', $uuid);
    }
    public function setLeaseAction($uuid)
    {
        return $this->setBase('lease', 'leases.lease', $uuid);
    }
    public function toggleLeaseAction($uuid)
    {
        return $this->toggleBase('leases.lease', $uuid);
    }
}
