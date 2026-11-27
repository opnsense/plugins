<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
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
 */

namespace OPNsense\HCloudDNS;

use OPNsense\Base\IndexController as BaseIndexController;

/**
 * Class IndexController
 * @package OPNsense\HCloudDNS
 */
class IndexController extends BaseIndexController
{
    /**
     * Main page with tabbed interface (v2)
     */
    public function indexAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/index');
        $this->view->generalForm = $this->getForm('general');
        $this->view->gatewayForm = $this->getForm('dialogGateway');
        $this->view->entryForm = $this->getForm('dialogEntry');
        $this->view->accountForm = $this->getForm('dialogAccount');
        $this->view->scheduledForm = $this->getForm('dialogScheduled');
        $this->view->entrySettingsForm = $this->getForm('dialogEntrySettings');
        $this->view->failoverForm = $this->getForm('failover');
        $this->view->dyndnsSettingsForm = $this->getForm('dyndnsSettings');
    }

    /**
     * Gateways management page (standalone, optional)
     */
    public function gatewaysAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/gateways');
        $this->view->gatewayForm = $this->getForm('dialogGateway');
    }

    /**
     * Zone selection page (standalone, optional)
     */
    public function zonesAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/zones');
    }

    /**
     * DNS entries management page (standalone, optional)
     */
    public function entriesAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/entries');
        $this->view->entryForm = $this->getForm('dialogEntry');
    }

    /**
     * Accounts management page (legacy)
     */
    public function accountsAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/accounts');
        $this->view->accountForm = $this->getForm('dialogAccount');
    }

    /**
     * Full DNS Management page - manage all zones and record types
     */
    public function dnsAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/dns');
    }

    /**
     * DNS Change History page - track all DNS modifications
     */
    public function historyAction()
    {
        $this->view->pick('OPNsense/HCloudDNS/history');
    }
}
