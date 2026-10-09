<?php

namespace OPNsense\DynDNS\Migrations;

use OPNsense\Base\BaseModelMigration;

class M1_5_2 extends BaseModelMigration
{
    public function run($model)
    {
        foreach ($model->accounts->account->iterateItems() as $account) {
            $service = (string)$account->service;
            if ($service == 'desec-v4' || $service == 'desec-v6') {
                /*
                 * Older deSEC entries used "password" to store the token secret;
                 * the deSEC account password was never supported. Copy the value
                 * to the explicit field, but leave the old value so unchanged
                 * migrated entries can roll back. Entries created after this
                 * migration only use token_secret.
                 */
                $legacy_token = $account->password->getValue();
                if ((string)$account->token_secret == '' && $legacy_token != '') {
                    $account->token_secret = $legacy_token;
                }
                /*
                 * deSEC never used "username" as an account login. If set at
                 * all, it could only mirror Hostname(s), which is already the
                 * authoritative update target.
                 */
                $account->username = '';
                /*
                 * The original deSEC path deleted the opposite address family
                 * when "preserve" was omitted. Existing accounts keep that
                 * behavior; new accounts get the model defaults and preserve.
                 */
                if ($service == 'desec-v4') {
                    $account->prune_aaaa = '1';
                } else {
                    $account->prune_a = '1';
                }
            }
        }
    }
}
