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

namespace OPNsense\HCloudDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;

/**
 * Class AccountsController
 * @package OPNsense\HCloudDNS\Api
 */
class AccountsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\HCloudDNS\HCloudDNS';
    protected static $internalModelName = 'hclouddns';

    /**
     * Search accounts
     * @return array
     */
    public function searchItemAction()
    {
        return $this->searchBase(
            'accounts.account',
            ['enabled', 'name', 'apiType', 'description'],
            'name'
        );
    }

    /**
     * Get single account
     * @param string $uuid
     * @return array
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase('account', 'accounts.account', $uuid);
    }

    /**
     * Check if token already exists in another account
     * @param string $token the token to check
     * @param string $excludeUuid optional UUID to exclude (for updates)
     * @return string|null account name if duplicate found, null otherwise
     */
    private function findDuplicateToken($token, $excludeUuid = null)
    {
        if (empty($token)) {
            return null;
        }

        $mdl = $this->getModel();
        foreach ($mdl->accounts->account->iterateItems() as $uuid => $account) {
            if ($excludeUuid !== null && $uuid === $excludeUuid) {
                continue;
            }
            if ((string)$account->apiToken === $token) {
                return (string)$account->name;
            }
        }
        return null;
    }

    /**
     * Add new account
     * @return array
     */
    public function addItemAction()
    {
        // Check for duplicate token before adding
        $postData = $this->request->getPost('account');
        if (is_array($postData) && !empty($postData['apiToken'])) {
            $existingAccount = $this->findDuplicateToken($postData['apiToken']);
            if ($existingAccount !== null) {
                return [
                    'status' => 'error',
                    'validations' => [
                        'account.apiToken' => sprintf('This token is already used by account "%s"', $existingAccount)
                    ]
                ];
            }
        }
        return $this->addBase('account', 'accounts.account');
    }

    /**
     * Update account
     * @param string $uuid
     * @return array
     */
    public function setItemAction($uuid)
    {
        // Check for duplicate token before updating
        $postData = $this->request->getPost('account');
        if (is_array($postData) && !empty($postData['apiToken'])) {
            $existingAccount = $this->findDuplicateToken($postData['apiToken'], $uuid);
            if ($existingAccount !== null) {
                return [
                    'status' => 'error',
                    'validations' => [
                        'account.apiToken' => sprintf('This token is already used by account "%s"', $existingAccount)
                    ]
                ];
            }
        }
        return $this->setBase('account', 'accounts.account', $uuid);
    }

    /**
     * Delete account and all associated DNS entries (cascade delete)
     * @param string $uuid
     * @return array
     */
    public function delItemAction($uuid)
    {
        if (empty($uuid)) {
            return ['status' => 'error', 'message' => 'Invalid UUID'];
        }

        $mdl = $this->getModel();

        // Find and delete all entries associated with this account
        $entriesToDelete = [];
        foreach ($mdl->entries->entry->iterateItems() as $entryUuid => $entry) {
            if ((string)$entry->account === $uuid) {
                $entriesToDelete[] = $entryUuid;
            }
        }

        // Delete associated entries
        $deletedEntries = 0;
        foreach ($entriesToDelete as $entryUuid) {
            $mdl->entries->entry->del($entryUuid);
            $deletedEntries++;
        }

        // Now delete the account itself
        $result = $this->delBase('accounts.account', $uuid);

        // Add info about deleted entries to result
        if ($deletedEntries > 0) {
            $result['deletedEntries'] = $deletedEntries;
            $result['message'] = "Account deleted along with $deletedEntries associated DNS entries";
        }

        return $result;
    }

    /**
     * Toggle account enabled status
     * @param string $uuid
     * @param int $enabled
     * @return array
     */
    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase('accounts.account', $uuid, $enabled);
    }

    /**
     * Get count of entries associated with an account
     * @param string $uuid
     * @return array
     */
    public function getEntryCountAction($uuid = null)
    {
        if (empty($uuid)) {
            return ['status' => 'error', 'count' => 0];
        }

        $mdl = $this->getModel();
        $count = 0;
        $entries = [];

        foreach ($mdl->entries->entry->iterateItems() as $entryUuid => $entry) {
            if ((string)$entry->account === $uuid) {
                $count++;
                $entries[] = (string)$entry->recordName . '.' . (string)$entry->zoneName;
            }
        }

        return [
            'status' => 'ok',
            'count' => $count,
            'entries' => $entries
        ];
    }
}
