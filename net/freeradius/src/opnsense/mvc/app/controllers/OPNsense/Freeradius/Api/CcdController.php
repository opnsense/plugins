<?php

/*
 * Copyright (C) 2018 EugenMayer
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

use \OPNsense\Base\ApiControllerBase;
use OPNsense\Freeradius\common\CCD;
use OPNsense\Freeradius\common\OpenVpn;
use OPNsense\Freeradius\User;

/**
 * Class CcdController
 * @property mixed request
 * @package OPNsense\Freeradius
 */
class CcdController extends ApiControllerBase
{
    /**
     * regenerates all CCDs for the configred users
     */
    public function regenerateAction()
    {
        if ($this->request->isPost()) {
            $dynamicCCDs = $this->getFreeradiusUsersAsCCDs();
            if (count($dynamicCCDs)) {
                OpenVpn::generateCCDconfigurationOnDisk($dynamicCCDs);
            }
            return array("status" => "ok", "generated_count" => count($dynamicCCDs));
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * @return object[] array of all users
     */
    private function getFreeradiusUsers()
    {
        $usersMdl = new User();

        $users = [];
        foreach ($usersMdl->getNodes() as $user) {
            $users[] = (object)array_pop($user['user']);
        }

        return $users;
    }

    /**
     * @return CCD[]
     */
    private function getFreeradiusUsersAsCCDs()
    {
        $users = $this->getFreeradiusUsers();
        $ccds = [];

        foreach ($users as $user) {
            $ccds[] = CCD::fromFreeradiusUsers($user);
        }
        return $ccds;
    }

}
