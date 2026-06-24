<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

namespace OPNsense\Nebula;

use OPNsense\Base\IndexController as BaseIndexController;

class FirewallController extends BaseIndexController
{
    public function indexAction()
    {
        $this->view->title = gettext('Nebula');

        // Firewall-rule grid + edit dialog (standard CRUD via UIBootgrid).
        $this->view->formDialogFirewallRule = $this->getForm('dialogFirewallRule');
        // Append a computed "Match" column (rendered by the nebula_fw_match
        // formatter). Done here in PHP: a Volt `formGrid + {'fields': ...}` merge
        // is a PHP array union that keeps the LEFT operand's existing 'fields'
        // and silently drops the override, so the extra column never renders.
        $grid = $this->getFormGrid('dialogFirewallRule');
        $grid['fields'][] = [
            'column-id'  => 'match',
            'label'      => gettext('Match'),
            'type'       => 'string',
            'visible'    => 'true',
            'sortable'   => 'false',
            'identifier' => 'false',
            'formatter'  => 'nebula_fw_match',
        ];
        $this->view->formGridFirewallRule = $grid;

        $this->view->pick('OPNsense/Nebula/firewall');
    }
}
