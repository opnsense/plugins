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

class BlocklistController extends BaseIndexController
{
    public function indexAction()
    {
        $this->view->title = gettext('Nebula');

        // Blocklist-entry grid + edit dialog (standard CRUD via UIBootgrid).
        $this->view->formDialogBlocklistEntry = $this->getForm('dialogBlocklistEntry');
        // The "Applies to" column resolves scope/instance to a description in
        // BlocklistController (API) searchItemAction, so it renders straight from
        // the row data. The "Certificate" column is computed (each fingerprint
        // cross-referenced against the configured certs); append it in PHP because
        // a Volt `formGrid + {'fields': ...}` merge silently drops the override
        // (same reason as the Firewall "Match" column).
        $grid = $this->getFormGrid('dialogBlocklistEntry');
        $grid['fields'][] = [
            'column-id'  => 'certificate',
            'label'      => gettext('Certificate'),
            'type'       => 'string',
            'visible'    => 'true',
            'sortable'   => 'false',
            'identifier' => 'false',
        ];
        $this->view->formGridBlocklistEntry = $grid;

        // Bulk-import dialog (custom action — manual POST, not standard CRUD).
        $this->view->formBlocklistImport = $this->getForm('dialogBlocklistImport');

        $this->view->pick('OPNsense/Nebula/blocklist');
    }
}
