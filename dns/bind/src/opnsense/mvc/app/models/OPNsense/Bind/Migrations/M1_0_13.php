<?php

/*
 * Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms with or without
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
 * INTERRUPTION) HOWEVER CAUSED BY ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Bind\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Bind\General;

class M1_0_13 extends BaseModelMigration
{
    /**
     * Migrate the legacy general.forwarders CSV into the new Forwarder model's
     * forwarders.dns grid. This migration is dispatched by the General model
     * (whose version is bumped to 1.0.13); it reads general.forwarders from the
     * config and writes rows into the Forwarder model's config node
     * (OPNsense.bind.forwarder.forwarders.dns) directly, since the migration
     * runs under General, not Forwarder. The Forwarder model assigns UUIDs to
     * the rows on its next load.
     * @param $model
     */
    public function run($model)
    {
        if (!($model instanceof General)) {
            return;
        }

        $config = Config::getInstance()->object();

        if (empty($config->OPNsense->bind)) {
            return;
        }

        $bindConfig = $config->OPNsense->bind;

        if (empty($bindConfig->general->forwarders)) {
            return;
        }

        $legacy = trim((string)$bindConfig->general->forwarders);
        if ($legacy === '') {
            return;
        }

        /* Ensure the Forwarder model's config nodes exist (forwarder.forwarders). */
        if (empty($bindConfig->forwarder)) {
            $bindConfig->addChild('forwarder');
        }
        if (empty($bindConfig->forwarder->forwarders)) {
            $bindConfig->forwarder->addChild('forwarders');
        }

        $forwarders = $bindConfig->forwarder->forwarders;

        /* Only migrate once: skip if the new grid already has rows. ArrayField
         * rows are direct children named "dns" under the "forwarders" container
         * (matching how Acl rows are <acl> children of <acls>). NOTE: do not use
         * $forwarders->children('dns') here — SimpleXMLElement::children($x)
         * treats its argument as a namespace prefix, not a tag name, so it
         * always returns 0 for plain-named children. Use the property accessor
         * instead. */
        if (count($forwarders->dns) > 0) {
            return;
        }

        foreach (explode(',', $legacy) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $ip = $token;
            $port = '53';

            /* ip:port socket notation (e.g. 127.0.0.1:5300) */
            if (strpos($token, ':') !== false) {
                $parts = explode(':', $token, 2);
                $candidateIp = $parts[0];
                $candidatePort = $parts[1];

                if (ctype_digit($candidatePort) && $candidatePort >= 1 && $candidatePort <= 65535) {
                    $ip = $candidateIp;
                    $port = $candidatePort;
                } else {
                    /* Falls through for bare IPv6 literals (e.g. 2001:db8::1) and for
                     * genuinely malformed ports; in both cases keep the full token as
                     * the IP and default the port to 53. */
                    syslog(LOG_WARNING, sprintf('BIND migration: no valid port found in "%s", defaulting to 53.', $token));
                }
            }

            /* Each row is a <dns> element directly under <forwarders>, with a uuid
             * attribute (matching the ArrayField config shape used by Acl/etc.). */
            $row = $forwarders->addChild('dns');
            $row->addAttribute('uuid', sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            ));
            $row->addChild('enabled', '1');
            $row->addChild('ip', $ip);
            $row->addChild('port', $port);
        }

        /* Clear the legacy field so it no longer shadows the grid. */
        $bindConfig->general->forwarders = '';
    }
}

