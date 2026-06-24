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

class InstancesController extends BaseIndexController
{
    public function indexAction()
    {
        $this->view->title = gettext('Nebula');

        // NOTE: do NOT pass a grid_id to getFormGrid() — let the table id default
        // to the form name ('dialogInstance'). With separate pages there is no
        // tab-pane <div> to collide with, so the default id is unique.
        $this->view->formDialogInstance = $this->getForm('dialogInstance');

        // Build the Instances grid columns EXPLICITLY rather than from the form.
        // getFormGrid() turns every <field> with an <id> into a column unless it
        // carries <grid_view><ignore>true</ignore></grid_view> — and the model
        // exposes the whole Nebula config surface (60+ fields, several with no
        // grid_view at all), so left to itself the grid is unusably wide. Instead
        // we take getFormGrid's output only to reuse its column definitions
        // (label/formatter/width for the fields we want), then assemble a short,
        // ordered whitelist. Every other field stays editable in the dialog; it
        // just is not a grid column. Two columns are computed (no backing model
        // field) so they could not be declared in the form at all:
        //   - "Listen"  combined host:port, IPv6-bracketed (nebula_listen)
        //   - "Status"  live running indicator (nebula_status)
        $grid = $this->getFormGrid('dialogInstance');

        // Index the auto-generated columns by id, and keep the hidden uuid
        // identifier column bootgrid needs for row identity.
        $byId = [];
        $identifier = null;
        foreach ($grid['fields'] as $field) {
            if (($field['identifier'] ?? '') === 'true') {
                $identifier = $field;
            }
            if (!empty($field['column-id'])) {
                $byId[$field['column-id']] = $field;
            }
        }

        // "Interface" — the system device name (tun_name). It is no longer an
        // editable form field (auto-managed, read-only, like wireguard's wgN), so
        // it is injected here as a plain column; the search endpoint returns
        // tun_name in the row data.
        $interfaceCol = [
            'column-id'  => 'tun_name',
            'label'      => gettext('Interface'),
            'type'       => 'string',
            'visible'    => 'true',
            'sortable'   => 'true',
            'identifier' => 'false',
            'width'      => '10em',
        ];
        $listenCol = [
            'column-id'  => 'listen',
            'label'      => gettext('Listen'),
            'type'       => 'string',
            'visible'    => 'true',
            'sortable'   => 'false',
            'identifier' => 'false',
            'formatter'  => 'nebula_listen',
        ];
        $statusCol = [
            'column-id'  => 'status',
            'label'      => gettext('Status'),
            'type'       => 'string',
            'visible'    => 'true',
            'sortable'   => 'false',
            'identifier' => 'false',
            'width'      => '5em',
            'formatter'  => 'nebula_status',
        ];

        // The visible column set, left to right. 'listen' and 'status' are the
        // computed columns; the rest reuse the form-derived definitions.
        $fields = [];
        if ($identifier !== null) {
            $fields[] = $identifier; // hidden uuid column (row identity)
        }
        if (isset($byId['enabled'])) {
            $fields[] = $byId['enabled'];
        }
        $fields[] = $interfaceCol; // Interface (device name) — injected, not in form
        foreach (['description', 'am_lighthouse'] as $id) {
            if (isset($byId[$id])) {
                $fields[] = $byId[$id];
            }
        }
        $fields[] = $listenCol;
        if (isset($byId['certref'])) {
            $fields[] = $byId['certref'];
        }
        $fields[] = $statusCol;

        $grid['fields'] = $fields;
        $this->view->formGridInstance = $grid;

        $this->view->pick('OPNsense/Nebula/instances');
    }
}
