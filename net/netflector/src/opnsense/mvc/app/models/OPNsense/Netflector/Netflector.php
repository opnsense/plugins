<?php

/*
 * Copyright (C) 2026 Sergii Bogomolov
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

namespace OPNsense\Netflector;

use OPNsense\Base\BaseModel;
use Phalcon\Messages\Message;

/**
 * Two rules the model XML cannot express: the pair collision needs every enabled entry at once, and
 * the CARP virtual IP never reaches the daemon, rc.conf.d gates on it. The daemon refuses everything
 * else itself, at start, with the reason in the log.
 */
class Netflector extends BaseModel
{
    /** Families that carry IPv4, and those that carry IPv6. Mirrors AddressFamily::uses_ipv4 / uses_ipv6. */
    private const IPV4_FAMILIES = ['default', 'dual', 'ipv4'];
    private const IPV6_FAMILIES = ['default', 'dual', 'ipv6'];

    /** The daemon's WoL ports when the entry does not name any. */
    private const DEFAULT_WOL_PORTS = ['7', '9'];

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        // Every pair, not only the edited entry: the one that creates a collision is often not the one
        // being saved.
        $active = [];
        foreach ($this->reflectors->reflector->iterateItems() as $entry) {
            if ($entry->enabled->isEqual('1')) {
                $active[] = $entry;
            }
        }
        foreach ($active as $i => $a) {
            foreach (array_slice($active, $i + 1) as $b) {
                $this->validatePair($a, $b, $messages);
            }
        }

        // Checked even when the field was not edited: deleting the virtual IP on another page is what
        // invalidates it.
        $depends_on = $this->general->carp_depend_on->getValue();
        if ($depends_on !== '' && !$this->carpVipExists($depends_on)) {
            $messages->appendMessage(new Message(
                gettext('The selected CARP virtual IP no longer exists.'),
                'netflector.general.carp_depend_on'
            ));
        }

        // No "at least one entry" rule: rc.conf.d arms the service only with an entry on, so the last
        // reflector can be removed without switching the service off first.
        return $messages;
    }

    /** An entry by name; the entry being added has none until it is saved. */
    private static function describe($entry)
    {
        $name = trim($entry->name->getValue());

        return $name !== '' ? sprintf(gettext('"%s"'), $name) : gettext('This entry');
    }

    /**
     * VirtualIPField offers only the virtual IPs that exist when the form is drawn; the stored value
     * outlives them.
     */
    private function carpVipExists($uuid)
    {
        foreach ((new \OPNsense\Interfaces\Vip())->vip->iterateItems() as $key => $vip) {
            if ($key === $uuid && $vip->mode->isEqual('carp')) {
                return true;
            }
        }

        return false;
    }

    /** Mirrors the daemon's check_conflicts / Reflector::conflicts_with. */
    private function validatePair($a, $b, $messages)
    {
        $protocol = self::conflictingProtocol($a, $b);
        if ($protocol !== null) {
            // Both entries named: toggling several at once reports the text alone, with no field.
            $messages->appendMessage(new Message(
                sprintf(
                    gettext('%s would reflect %s between the same interfaces as %s, for overlapping ' .
                            'devices and address families, so each packet would be reflected twice.'),
                    self::describe($b),
                    $protocol,
                    self::describe($a)
                ),
                $b->__reference . '.source_if'
            ));
        }
    }

    private static function conflictingProtocol($a, $b)
    {
        if (
            !$a->source_if->isEqual($b->source_if->getValue()) ||
            !$a->target_if->isEqual($b->target_if->getValue())
        ) {
            return null;
        }
        if (!self::macsOverlap($a->macs->getValue(), $b->macs->getValue())) {
            return null;
        }
        if (!self::familiesOverlap($a->address_family->getValue(), $b->address_family->getValue())) {
            return null;
        }

        if (
            $a->wol->isEqual('1') && $b->wol->isEqual('1') &&
            self::portsOverlap($a->wol_ports->getValue(), $b->wol_ports->getValue())
        ) {
            return gettext('Wake-on-LAN');
        }
        if ($a->mdns->isEqual('1') && $b->mdns->isEqual('1')) {
            return gettext('mDNS');
        }
        if ($a->ssdp->isEqual('1') && $b->ssdp->isEqual('1')) {
            return gettext('SSDP');
        }
        if ($a->wsd->isEqual('1') && $b->wsd->isEqual('1')) {
            return gettext('WS-Discovery');
        }

        return null;
    }

    /** An empty port list means the daemon's defaults, so two blank lists overlap. */
    private static function portsOverlap($a, $b)
    {
        $set_a = self::toSet($a) ?: self::DEFAULT_WOL_PORTS;
        $set_b = self::toSet($b) ?: self::DEFAULT_WOL_PORTS;
        return count(array_intersect($set_a, $set_b)) > 0;
    }

    /** An empty MAC selection means the whole network, which overlaps with any other selection. */
    private static function macsOverlap($a, $b)
    {
        $set_a = self::toSet($a);
        $set_b = self::toSet($b);
        if (empty($set_a) || empty($set_b)) {
            return true;
        }
        return count(array_intersect($set_a, $set_b)) > 0;
    }

    private static function familiesOverlap($a, $b)
    {
        return (self::usesIpv4($a) && self::usesIpv4($b)) || (self::usesIpv6($a) && self::usesIpv6($b));
    }

    private static function usesIpv4($family)
    {
        return in_array($family, self::IPV4_FAMILIES, true);
    }

    private static function usesIpv6($family)
    {
        return in_array($family, self::IPV6_FAMILIES, true);
    }

    private static function toSet($csv)
    {
        $out = [];
        foreach (explode(',', $csv) as $value) {
            $value = strtolower(trim($value));
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }
}
