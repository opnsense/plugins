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
 *    notice, this list of conditions in the documentation and/or other
 *    materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES, LOSS OF USE, DATA, OR PROFITS, OR BUSINESS
 * INTERRUPTION HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT INCLUDING NEGLIGENCE OR OTHERWISE
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Quagga\Menu;

use OPNsense\Base\Menu\MenuContainer;
use OPNsense\Core\Config;

class Menu extends MenuContainer
{
    public function collect()
    {
        $config = Config::getInstance()->object();

        if (!empty($config->OPNsense?->quagga?->general?->manual_config)) {
            return;
        }

        $this->appendItem('Routing', 'RIP', [
            'url' => '/ui/quagga/rip/index',
            'fixedname' => gettext('RIP'),
            'cssClass' => 'fa fa-expand fa-fw',
            'order' => 10,
        ]);

        $this->appendItem('Routing', 'OSPF', [
            'url' => '/ui/quagga/ospf/index',
            'fixedname' => gettext('OSPF'),
            'cssClass' => 'fa fa-map fa-fw',
            'order' => 20,
        ]);

        $this->appendItem('Routing', 'OSPFv3', [
            'url' => '/ui/quagga/ospf6/index',
            'fixedname' => gettext('OSPFv3'),
            'cssClass' => 'fa fa-map fa-fw',
            'order' => 25,
        ]);

        $this->appendItem('Routing', 'BGP', [
            'url' => '/ui/quagga/bgp/index',
            'fixedname' => gettext('BGP'),
            'cssClass' => 'fa fa-globe fa-fw',
            'order' => 40,
        ]);

        $this->appendItem('Routing', 'BFD', [
            'url' => '/ui/quagga/bfd/index',
            'fixedname' => gettext('BFD'),
            'cssClass' => 'fa fa-exchange fa-fw',
            'order' => 50,
        ]);

        $this->appendItem('Routing', 'STATIC', [
            'url' => '/ui/quagga/static/index',
            'fixedname' => gettext('STATIC'),
            'cssClass' => 'fa fa-expand fa-fw',
            'order' => 60,
        ]);
    }
}
