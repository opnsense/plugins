<?php

/*
 * Copyright (C) 2026 VEQNORA
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

namespace OPNsense\PPPoEServer;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * PPPoE Access Concentrator model
 * @package OPNsense\PPPoEServer
 */
class PPPoEServer extends BaseModel
{
    /**
     * cross field validation: pool ranges, overlaps, gateway placement,
     * duplicate usernames and static address consistency.
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        // collect pools
        $pools = [];
        foreach ($this->pools->pool->iterateItems() as $uuid => $pool) {
            $start = ip2long((string)$pool->start);
            $end = ip2long((string)$pool->end);
            if ($start === false || $end === false) {
                continue; // base validators already flagged the field
            }
            if ($start > $end) {
                $messages->appendMessage(new Message(
                    gettext('Pool start address is higher than the end address.'),
                    $pool->start->__reference
                ));
                continue;
            }
            $pools[$uuid] = [
                'name' => (string)$pool->name,
                'enabled' => (string)$pool->enabled == '1',
                'start' => $start,
                'end' => $end,
                'ref' => $pool->start->__reference,
            ];
        }

        // overlap detection between enabled pools
        $seen = [];
        foreach ($pools as $uuid => $pool) {
            if (!$pool['enabled']) {
                continue;
            }
            foreach ($seen as $other) {
                if ($pool['start'] <= $other['end'] && $other['start'] <= $pool['end']) {
                    $messages->appendMessage(new Message(
                        sprintf(gettext('Pool range overlaps with pool "%s".'), $other['name']),
                        $pool['ref']
                    ));
                    break;
                }
            }
            $seen[] = $pool;
        }

        // AC checks: gateway must not fall inside the attached pool
        foreach ($this->acs->ac->iterateItems() as $ac) {
            $gateway = ip2long((string)$ac->gateway);
            $poolUuid = (string)$ac->pool;
            if ($gateway !== false && isset($pools[$poolUuid])) {
                if ($gateway >= $pools[$poolUuid]['start'] && $gateway <= $pools[$poolUuid]['end']) {
                    $messages->appendMessage(new Message(
                        gettext('Gateway address must be outside of the attached address pool.'),
                        $ac->gateway->__reference
                    ));
                }
                if ((string)$ac->enabled == '1' && !$pools[$poolUuid]['enabled']) {
                    $messages->appendMessage(new Message(
                        gettext('Attached address pool is disabled.'),
                        $ac->pool->__reference
                    ));
                }
            }
        }

        // user checks: unique usernames, static address not inside any enabled pool
        $usernames = [];
        foreach ($this->users->user->iterateItems() as $user) {
            $username = (string)$user->username;
            if ($username != '') {
                if (isset($usernames[$username])) {
                    $messages->appendMessage(new Message(
                        gettext('Username is already in use.'),
                        $user->username->__reference
                    ));
                }
                $usernames[$username] = true;
            }
            $staticip = ip2long((string)$user->staticip);
            if ($staticip !== false) {
                foreach ($pools as $pool) {
                    if ($pool['enabled'] && $staticip >= $pool['start'] && $staticip <= $pool['end']) {
                        $messages->appendMessage(new Message(
                            sprintf(gettext('Static address collides with dynamic pool "%s".'), $pool['name']),
                            $user->staticip->__reference
                        ));
                        break;
                    }
                }
            }
        }

        // RADIUS CoA: a shared secret is required when the listener is enabled
        if ((string)$this->radius->coa->enabled == '1' && (string)$this->radius->coa->secret == '') {
            $messages->appendMessage(new Message(
                gettext('A CoA shared secret is required when the CoA listener is enabled.'),
                $this->radius->coa->secret->__reference
            ));
        }

        // RADIUS: when enabled at least one active server must exist
        if ((string)$this->general->radiusauth == '1' || (string)$this->general->radiusacct == '1') {
            $active = 0;
            foreach ($this->radius->server->iterateItems() as $server) {
                if ((string)$server->enabled == '1') {
                    $active++;
                }
            }
            if ($active == 0) {
                $messages->appendMessage(new Message(
                    gettext('RADIUS is enabled but no active RADIUS server is configured.'),
                    $this->general->radiusauth->__reference
                ));
            }
        }

        return $messages;
    }
}
