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

namespace OPNsense\Netflector\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Config;

/**
 * The grid's CRUD. The *Base helpers carry the model's own validation through, so an entry the model
 * rejects (see Netflector::performValidation) comes back as a field error in the dialog rather than
 * being written and blowing up later at startup.
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'netflector';
    protected static $internalModelClass = '\OPNsense\Netflector\Netflector';

    public function searchReflectorAction()
    {
        return $this->searchBase(
            'reflectors.reflector',
            [
                'enabled', 'name', 'source_if', 'target_if', 'description',
                'wol', 'mdns', 'ssdp', 'dial', 'wsd', 'address_family',
            ]
        );
    }

    public function getReflectorAction($uuid = null)
    {
        return $this->getBase('reflector', 'reflectors.reflector', $uuid);
    }

    public function addReflectorAction()
    {
        return $this->addBase('reflector', 'reflectors.reflector');
    }

    public function setReflectorAction($uuid)
    {
        return $this->setBase('reflector', 'reflectors.reflector', $uuid);
    }

    public function delReflectorAction($uuid)
    {
        return $this->delBase('reflectors.reflector', $uuid);
    }

    /**
     * Toggle an entry, validating before the save.
     *
     * toggleBase() saves with validation disabled, which is fine for most models but not for ours:
     * enabling an entry can collide with an already-active one (same interface pair, overlapping
     * protocols or devices), and that pair rule lives in the model's validation.
     */
    public function toggleReflectorAction($uuids, $enabled = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }

        Config::getInstance()->lock();
        $model = $this->getModel();

        // The grid's "Enable selected" sends every checked row as one comma-joined request, so this
        // takes a list like toggleBase does. Flipping is only defined for a single entry: a list has
        // no shared previous state to flip from, which is why the grid always sends 0 or 1 with one.
        $uuids = !empty($uuids) ? explode(',', $uuids) : [];
        if (count($uuids) > 1 && $enabled === null) {
            throw new UserException(
                gettext('Toggling a list of entries needs an explicit enabled state.'),
                gettext('Netflector')
            );
        }

        $changed = false;
        $result = 'failed';
        foreach ($uuids as $uuid) {
            $node = $model->getNodeByReference('reflectors.reflector.' . $uuid);
            if ($node === null) {
                continue;
            }

            // Three cases, as toggleBase has them: an explicit 0 or 1 sets that value, no value at all
            // flips, and anything else is a malformed request that must not touch the entry.
            $was = (string)$node->enabled;
            if ($enabled === '0' || $enabled === '1') {
                $node->enabled = (string)$enabled;
            } elseif ($enabled === null) {
                $node->enabled = $was === '1' ? '0' : '1';
            } else {
                return ['result' => 'failed', 'changed' => false];
            }

            $changed = $changed || (string)$node->enabled !== $was;
            $result = (string)$node->enabled === '1' ? 'Enabled' : 'Disabled';
        }

        // A UserException, not a 'failed' result: the grid has no field to hang a validation message on,
        // so a plain failure would bounce the checkbox back with no word of why. This surfaces as a dialog.
        $messages = $model->performValidation();
        if (count($messages) > 0) {
            $texts = [];
            foreach ($messages as $message) {
                $texts[] = $message->getMessage();
            }
            throw new UserException(implode(' ', array_unique($texts)), gettext('Netflector'));
        }

        $this->save();

        return ['result' => $result, 'changed' => $changed];
    }
}
