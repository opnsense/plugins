<?php

/*
 * Copyright (C) 2026 cayossarian (Bill Flood)
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

namespace OPNsense\AvahiReflector;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;

class AvahiReflector extends BaseModel
{
    /**
     * The avahi-daemon INI parser reads lines with fgets() into a 256-byte
     * buffer, so a "key=value" line may be at most 255 characters. A longer
     * line is split, the remainder is parsed as a separate (malformed) line
     * and the daemon refuses to start.
     */
    private const CONFIG_LINE_MAX_LENGTH = 255;

    /**
     * Length of the "key=value" line avahi-daemon.conf would carry.
     */
    private static function configLineLength(string $key, string $value): int
    {
        return strlen($key) + 1 + strlen($value);
    }

    /**
     * The allow-interfaces value the template renders: each selected
     * interface's physical device, as OPNsense/Macros/interface.macro resolves it.
     */
    private function allowInterfaces(): string
    {
        $interfaces = Config::getInstance()->object()->interfaces;
        $physical = [];
        foreach (explode(',', (string)$this->interfaces) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $physical[] = isset($interfaces->$name->if) ? (string)$interfaces->$name->if : $name;
        }
        return implode(',', $physical);
    }

    /**
     * True when the mdns-repeater plugin is installed and enabled. Both bind
     * UDP 5353 and reflect the same traffic. The class check keeps a stale
     * config section left behind by an uninstalled mdns-repeater from blocking.
     */
    private static function mdnsRepeaterEnabled(): bool
    {
        if (!class_exists('OPNsense\MDNSRepeater\MDNSRepeater')) {
            return false;
        }
        $cfg = Config::getInstance()->object();
        return isset($cfg->OPNsense->MDNSRepeater->enabled) &&
            (string)$cfg->OPNsense->MDNSRepeater->enabled === '1';
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if ((string)$this->enabled === '1' && self::mdnsRepeaterEnabled()) {
            $messages->appendMessage(new Message(
                gettext('The mDNS Repeater service is enabled. Disable it before enabling the Avahi reflector; both listen on UDP port 5353.'),
                $this->enabled->getInternalXMLTagName()
            ));
        }

        $ipv4 = (string)$this->use_ipv4 === '1';
        $ipv6 = (string)$this->use_ipv6 === '1';
        if (!$ipv4 && !$ipv6) {
            $messages->appendMessage(new Message(
                gettext('Enable IPv4, IPv6 or both.'),
                $this->use_ipv4->getInternalXMLTagName()
            ));
        }
        if ((string)$this->reflect_ipv === '1' && !($ipv4 && $ipv6)) {
            $messages->appendMessage(new Message(
                gettext('Reflecting across IP versions requires both IPv4 and IPv6 to be enabled.'),
                $this->reflect_ipv->getInternalXMLTagName()
            ));
        }

        $allowInterfaces = $this->allowInterfaces();
        $length = self::configLineLength('allow-interfaces', $allowInterfaces);
        if ($length > self::CONFIG_LINE_MAX_LENGTH) {
            $messages->appendMessage(new Message(
                sprintf(
                    gettext('The selected interfaces render an allow-interfaces line of %d characters (%s); the avahi-daemon config parser accepts at most %d. Select fewer interfaces.'),
                    $length,
                    $allowInterfaces,
                    self::CONFIG_LINE_MAX_LENGTH
                ),
                $this->interfaces->getInternalXMLTagName()
            ));
        }

        $filters = (string)$this->reflect_filters;
        $length = self::configLineLength('reflect-filters', $filters);
        if ($length > self::CONFIG_LINE_MAX_LENGTH) {
            $messages->appendMessage(new Message(
                sprintf(
                    gettext('Reflect filters exceed the %d-character limit imposed by the avahi-daemon config parser (currently %d). Remove entries or drop the ".local" suffix; Avahi uses substring matching so the suffix is not required.'),
                    self::CONFIG_LINE_MAX_LENGTH - strlen('reflect-filters='),
                    strlen($filters)
                ),
                $this->reflect_filters->getInternalXMLTagName()
            ));
        }

        return $messages;
    }
}
