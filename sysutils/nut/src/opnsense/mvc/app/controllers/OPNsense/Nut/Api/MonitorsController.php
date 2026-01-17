<?php

/**
 *    Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
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

namespace OPNsense\Nut\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Util;

class MonitorsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Nut\Nut';
    protected static $internalModelName = 'nut';

    public function searchLocalMonitorAction()
    {
        return $this->searchBase("monitoring.local");
    }

    public function getLocalMonitorAction($uuid = null)
    {
        return $this->getBase("local", "monitoring.local", $uuid);
    }

    public function addLocalMonitorAction()
    {
        return $this->addBase("local", "monitoring.local");
    }

    public function setLocalMonitorAction($uuid)
    {
        return $this->setBase("local", "monitoring.local", $uuid);
    }

    public function delLocalMonitorAction($uuid)
    {
        return $this->delBase("monitoring.local", $uuid);
    }

    public function toggleLocalMonitorAction($uuid, $enabled = null)
    {
        return $this->toggleBase("monitoring.local", $uuid, $enabled);
    }

    public function statusLocalMonitorAction($uuid)
    {
        $mdl = $this->getModel();
        $monitor = $mdl->getNodeByReference("monitoring.local." . $uuid);
        if ($monitor == null) {
            return [];
        }

        $host = $mdl->getLoopbackListenAddress();
        $ups = $mdl->getNodeByReference("drivers.ups." . $monitor->ups);
        $response = (new Backend())->configdpRun("nut upsstatus", array("{$ups->name}@{$host}"));
        return array("response" => $response);
    }

    public function searchRemoteMonitorAction()
    {
        return $this->searchBase("monitoring.remote", null, "ups_name");
    }

    public function getRemoteMonitorAction($uuid = null)
    {
        return $this->getBase("remote", "monitoring.remote", $uuid);
    }

    public function addRemoteMonitorAction()
    {
        return $this->addBase("remote", "monitoring.remote");
    }

    public function setRemoteMonitorAction($uuid)
    {
        return $this->setBase("remote", "monitoring.remote", $uuid);
    }

    public function delRemoteMonitorAction($uuid)
    {
        return $this->delBase("monitoring.remote", $uuid);
    }

    public function toggleRemoteMonitorAction($uuid, $enabled = null)
    {
        return $this->toggleBase("monitoring.remote", $uuid, $enabled);
    }

    public function statusRemoteMonitorAction($uuid)
    {
        $mdl = $this->getModel();
        $monitor = $mdl->getNodeByReference("monitoring.remote." . $uuid);
        if ($node == null) {
            return [];
        }
        if (Util::isIpv6Address($monitor->hostname)) {
            $host = "[" . $monitor->hostname . "]:" . $monitor->port;
        } else {
            $host = $monitor->hostname . ":" . $monitor->port;
        }
        $response = (new Backend())->configdpRun("nut upsstatus", array("{$monitor->ups_name}@{$host}"));
        return array("response" => $response);
    }
}
