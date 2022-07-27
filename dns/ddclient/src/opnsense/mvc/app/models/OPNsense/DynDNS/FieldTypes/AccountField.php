<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\DynDNS\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\FieldTypes\IntegerField;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class AccountField extends ArrayField
{
    private static $current_stats = null;

    private function addStatsFields($node)
    {
        // generate new unattached fields, which are only usable to read data from (not synched to config.xml)
        $current_ip = new TextField();
        $current_ip->setInternalIsVirtual();
        $current_mtime = new TextField();
        $current_mtime->setInternalIsVirtual();

        if (!empty((string)$node->hostnames)) {
            foreach (explode(",", (string)$node->hostnames) as $hostname) {
                if (!empty(self::$current_stats[$hostname]) && !empty(self::$current_stats[$hostname]['ip'])) {
                    $stats = self::$current_stats[$hostname];
                    $current_ip->setValue($stats['ip']);
                    $current_mtime->setValue(date('c', $stats['mtime']));
                    break;
                }
            }
        }
        $node->addChildNode('current_ip', $current_ip);
        $node->addChildNode('current_mtime', $current_mtime);
    }

    protected function actionPostLoadingEvent()
    {
        if (self::$current_stats === null) {
            self::$current_stats = [];
            $stats = json_decode((new Backend())->configdRun('ddclient statistics'), true);
            if (!empty($stats) && !empty($stats['hosts'])) {
                self::$current_stats = $stats['hosts'];
            }
        }
        foreach ($this->internalChildnodes as $node) {
            if (!$node->getInternalIsVirtual()) {
                $this->addStatsFields($node);
            }
        }
        return parent::actionPostLoadingEvent();
    }
}
