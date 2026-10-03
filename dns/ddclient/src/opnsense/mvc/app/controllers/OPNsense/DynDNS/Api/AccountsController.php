<?php

/**
 *    Copyright (C) 2021 Deciso B.V.
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

namespace OPNsense\DynDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class AccountsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'account';
    protected static $internalModelClass = 'OPNsense\DynDNS\DynDNS';

    public function searchItemAction()
    {
        $result = $this->searchBase(
            "accounts.account",
            [
              'enabled', 'service', 'description', 'username', 'hostnames', 'use_interface',
              'interface', 'protocol', 'current_ip', 'current_mtime'
            ],
            "description"
        );
        foreach ($result['rows'] as &$row) {
            if ($row['service'] == 'Custom') {
                $row['service'] = 'Custom (' . $row['protocol'] . ')';
            }
            unset($row['protocol']);
        }
        return $result;
    }

    public function setItemAction($uuid)
    {
        return $this->setBase("account", "accounts.account", $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase("account", "accounts.account");
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("account", "accounts.account", $uuid);
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("accounts.account", $uuid);
    }

    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase("accounts.account", $uuid, $enabled);
    }

    /**
     * Force refresh the given accounts (native backend): their cached state is cleared while the service is
     * stopped, after which the service updates them on startup. The grid shows the outcome per account.
     * @param string $uuids comma separated list of account uuids
     * @return array status
     */
    public function forceRefreshAction($uuids = null)
    {
        $result = ['status' => 'failed'];
        if (!$this->request->isPost()) {
            return $result;
        }
        $mdl = $this->getModel();
        $enabled = [];
        foreach ($mdl->accounts->account->iterateItems() as $uuid => $account) {
            if (!empty((string)$account->enabled)) {
                $enabled[] = $uuid;
            }
        }
        $uuids = array_values(array_unique(array_filter(explode(',', (string)$uuids))));
        if (empty((string)$mdl->general->enabled) || (string)$mdl->general->backend != 'opnsense') {
            $result['message'] = gettext('Force refresh requires the native backend to be enabled.');
        } elseif (empty($uuids) || !empty(array_diff($uuids, $enabled))) {
            // only existing, enabled accounts
            $result['message'] = gettext('Invalid account.');
        } else {
            $backend = new Backend();
            $backend->configdRun('ddclient stop');
            $cleared = trim($backend->configdpRun('ddclient force_refresh', [implode(',', $uuids)])) == 'OK';
            // always start the service again, also when clearing failed
            $backend->configdRun('ddclient start');
            // the service reports running shortly after start returns, allow it up to 10 seconds
            $running = false;
            for ($retries = 10; !$running && $retries > 0; $retries--) {
                $running = strpos($backend->configdRun('ddclient status'), 'is running') !== false;
                if (!$running) {
                    sleep(1);
                }
            }
            if (!$cleared) {
                $result['message'] = gettext('Unable to clear the cached state, see the log for details.');
            } elseif (!$running) {
                $result['message'] = gettext('The Dynamic DNS service did not start, see the log for details.');
            } else {
                $result['status'] = 'ok';
            }
        }
        return $result;
    }
}
