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

class CertificatesController extends BaseIndexController
{
    public function indexAction()
    {
        $this->view->title = gettext('Nebula');

        // Certificate grid (uses dialogCertificate for edit); default table id.
        $this->view->formDialogCertificate = $this->getForm('dialogCertificate');
        // Insert a "Fingerprint" column (first 8 hex) as the second column — right
        // after Name — to disambiguate like-named certs. searchItemAction now
        // returns each cert's own fingerprint. Splice so it lands second.
        $grid = $this->getFormGrid('dialogCertificate');
        array_splice($grid['fields'], 1, 0, [[
            'column-id'  => 'fingerprint',
            'label'      => gettext('Fingerprint'),
            'type'       => 'string',
            'visible'    => 'true',
            'sortable'   => 'false',
            'identifier' => 'false',
            'width'      => '9em',
            'formatter'  => 'nebula_fp8',
        ]]);
        $this->view->formGridCertificate   = $grid;

        // Custom-action dialogs (sign / import — not standard CRUD).
        $this->view->formCertificateSign   = $this->getForm('dialogCertificateSign');
        $this->view->formCertificateImport = $this->getForm('dialogCertificateImport');

        $this->view->pick('OPNsense/Nebula/certificates');
    }
}
