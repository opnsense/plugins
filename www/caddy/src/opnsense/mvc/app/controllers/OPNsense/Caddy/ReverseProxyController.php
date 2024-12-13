<?php

/**
 *    Copyright (C) 2023-2024 Cedrik Pischem
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Caddy;

use OPNsense\Base\IndexController;

class ReverseProxyController extends IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Caddy/reverse_proxy');

        $formDialogReverseProxy = $this->getForm("dialogReverseProxy");
        $this->view->formDialogReverseProxy = $this->preprocessFields($formDialogReverseProxy, 'reverse.');

        $formDialogSubdomain = $this->getForm("dialogSubdomain");
        $this->view->formDialogSubdomain = $this->preprocessFields($formDialogSubdomain, 'subdomain.');

        $formDialogHandle = $this->getForm("dialogHandle");
        $this->view->formDialogHandle = $this->preprocessFields($formDialogHandle, 'handle.');

        $formDialogAccessList = $this->getForm("dialogAccessList");
        $this->view->formDialogAccessList = $this->preprocessFields($formDialogAccessList, 'accesslist.');

        $formDialogBasicAuth = $this->getForm("dialogBasicAuth");
        $this->view->formDialogBasicAuth = $this->preprocessFields($formDialogBasicAuth, 'basicauth.');

        $formDialogHeader = $this->getForm("dialogHeader");
        $this->view->formDialogHeader = $this->preprocessFields($formDialogHeader, 'header.');
    }

    /**
     * Preprocess fields to add 'column_id' by stripping prefixes and sort by sequence
     *
     * @param array $fields The fields array to process
     * @param string $prefixToRemove The prefix to strip from 'id'
     * @return array The processed fields array
     */
    private function preprocessFields($fields, $prefixToRemove)
    {
        // Add 'column_id' by stripping the prefix
        foreach ($fields as &$field) {
            if (isset($field['id'])) {
                $field['column_id'] = strpos($field['id'], $prefixToRemove) === 0
                    ? substr($field['id'], strlen($prefixToRemove))
                    : $field['id'];
            }
        }

        // Sort fields by 'sequence', defaulting to 0 if not set
        usort($fields, function ($a, $b) {
            return ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0);
        });

        return $fields;
    }
}
