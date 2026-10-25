<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\DHCPv4\Menu;

use OPNsense\Base\Menu\MenuContainer;
use OPNsense\Core\Config;

class Menu extends MenuContainer
{
    public function collect()
    {
        $config = Config::getInstance()->object();

        // collect interfaces for dynamic (interface) menu tabs...
        $iftargets = ['dhcp4' => [], 'dhcp6' => []];
        if ($config->interfaces->count() > 0) {
            foreach ($config->interfaces->children() as $key => $node) {
                // "Services: DHCPv[46]" menu tab:
                if (empty($node->virtual) && isset($node->enable)) {
                    if (!empty(filter_var($node->ipaddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))) {
                        $iftargets['dhcp4'][$key] = !empty($node->descr) ? (string)$node->descr : strtoupper($key);
                    }
                    if (!empty(filter_var($node->ipaddrv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) || (string)$node->ipaddrv6 == 'track6' || (string)$node->ipaddrv6 == 'idassoc6') {
                        $iftargets['dhcp6'][$key] = !empty($node->descr) ? (string)$node->descr : strtoupper($key);
                    }
                }
            }
        }

        foreach (array_keys($iftargets) as $tab) {
            natcasesort($iftargets[$tab]);
        }

        // add interfaces to "Services: DHCPv[46]" menu tab:
        $ordid = 0;
        foreach ($iftargets['dhcp4'] as $key => $descr) {
            $this->appendItem('Services.ISC_DHCPv4', $key, [
                'url' => '/services_dhcp.php?if=' . $key,
                'fixedname' => "[$descr]",
                'order' => $ordid++,
            ]);
            $this->appendItem('Services.ISC_DHCPv4.' . $key, 'Edit' . $key, [
                'url' => '/services_dhcp.php?if=' . $key . '&*',
                'visibility' => 'hidden',
            ]);
            $this->appendItem('Services.ISC_DHCPv4.' . $key, 'AddStatic' . $key, [
                'url' => '/services_dhcp_edit.php?if=' . $key,
                'visibility' => 'hidden',
            ]);
            $this->appendItem('Services.ISC_DHCPv4.' . $key, 'EditStatic' . $key, [
                'url' => '/services_dhcp_edit.php?if=' . $key . '&*',
                'visibility' => 'hidden',
            ]);
        }
        $ordid = 0;
        foreach ($iftargets['dhcp6'] as $key => $descr) {
            $this->appendItem('Services.ISC_DHCPv6', $key, [
                'url' => '/services_dhcpv6.php?if=' . $key,
                'fixedname' => "[$descr]",
                'order' => $ordid++,
            ]);
            $this->appendItem('Services.ISC_DHCPv6.' . $key, 'Add' . $key, [
                'url' => '/services_dhcpv6_edit.php?if=' . $key,
                'visibility' => 'hidden',
            ]);
            $this->appendItem('Services.ISC_DHCPv6.' . $key, 'Edit' . $key, [
                'url' => '/services_dhcpv6_edit.php?if=' . $key . '&*',
                'visibility' => 'hidden',
            ]);
        }
    }
}
